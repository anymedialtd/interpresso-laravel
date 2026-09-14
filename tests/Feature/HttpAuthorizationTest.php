<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Notifications\FlashMessage;
use AnyMedia\Interpresso\Tests\BaseTestCase;

class HttpAuthorizationTest extends BaseTestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_admin_endpoint_rejects_a_regular_translator_without_changing_data(): void
    {
        $translator = $this->createUser(Language::all());
        $language = Language::firstOrFail();
        $row = $language->translations()->create(['language_code' => $language->code, 'key' => 'authorization', 'shared_identifier' => 'json::authorization', 'type' => 'json', 'value' => 'original', 'needs_translation' => false]);
        Setting::firstOrFail()->update(['allow_deleting_languages' => true]);
        $tables = [config('interpresso.table_languages'), config('interpresso.table_translations'), config('interpresso.table_translators'), config('interpresso.table_translator_language'), config('interpresso.table_settings'), 'notifications', 'jobs', 'job_batches'];
        $snapshot = fn () => array_map(fn ($table) => DB::table($table)->get()->toJson(), $tables);
        $before = $snapshot();
        $this->actingAs($translator);
        // Keep an independent inventory: removing admin middleware must not
        // silently remove an endpoint from this authorization test.
        $adminRoutes = [
            'interpresso.languages.store', 'interpresso.languages.delete', 'interpresso.languages.import-languages', 'interpresso.languages.import-translations',
            'interpresso.languages.find-missing', 'interpresso.languages.approve', 'interpresso.languages.export', 'interpresso.languages.cancel-jobs',
            'interpresso.translations.approve-all', 'interpresso.translations.export', 'interpresso.translations.update-all', 'interpresso.translations.approve',
            'interpresso.translations.request', 'interpresso.translations.restore-request', 'interpresso.translations.restore',
            'interpresso.translators', 'interpresso.translators.edit', 'interpresso.translators.store', 'interpresso.translators.update', 'interpresso.translators.delete',
            'interpresso.translators.password', 'interpresso.translators.notify', 'interpresso.settings', 'interpresso.settings.update',
        ];
        foreach ($adminRoutes as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains('interpresso.admin', $route->gatherMiddleware(), $name);
        }
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            if (!in_array('interpresso.admin', $route->gatherMiddleware(), true)) continue;
            $parameters = array_intersect_key(['language' => $language->id, 'translator' => $translator->id, 'id' => $row->id, 'field' => 'allow_deleting_languages'], array_flip($route->parameterNames()));
            $url = route($route->getName(), $parameters);
            $method = in_array('POST', $route->methods(), true) ? 'post' : 'get';
            $this->{$method}($url, $method === 'post' ? [
                'translatedValue' => 'changed', 'language' => 'fr', 'allow_deleting_languages' => false,
                'email' => 'changed@example.test', 'first_name' => 'Changed', 'last_name' => 'Profile',
                'admin' => true, 'languages' => [$language->id], 'password' => 'new-password',
                'password_confirmation' => 'new-password', 'new_password' => 'new-password', 'new_password_confirmation' => 'new-password',
            ] : [])
                ->assertForbidden();
            $this->assertSame($before, $snapshot(), $route->getName() . ' changed data');
            $checked++;
        }
        $this->assertSame(24, $checked);
        foreach (['domains', 'enable_multi_host', 'db_loader', 'import_vendor', 'enable_pending_notifications',
            'enable_automatic_pending_notifications', 'enable_open_ai_translations', 'import_only_from_root_language', 'allow_deleting_languages'] as $field) {
            $this->post(route('interpresso.settings.update', $field), [$field => $field === 'domains' ? 'https://unauthorized.example' : true])->assertForbidden();
            $this->assertSame($before, $snapshot(), $field . ' changed data');
        }
    }

    #[Test]
    public function notification_ownership_is_checked_for_read_and_unread(): void
    {
        $admin = Translator::firstOrFail();
        $other = $this->createUser(Language::all());
        $admin->notifyNow(new FlashMessage('Admin private message'));
        $other->notifyNow(new FlashMessage('Translator private message'));
        $foreign = $admin->notifications()->firstOrFail();
        $own = $other->notifications()->firstOrFail();
        $this->actingAs($other);
        $this->getJson(route('interpresso.notifications'))->assertOk()->assertJsonCount(1, 'notifications')->assertDontSee('Admin private message');
        foreach ([true, false] as $read) {
            $this->postJson(route('interpresso.notifications.read', $foreign->id), ['read' => $read])->assertForbidden();
            $this->assertNull($foreign->fresh()->read_at);
        }
        $this->postJson(route('interpresso.notifications.read', $own->id))->assertOk()->assertJsonCount(0, 'notifications');
        $this->assertNotNull($own->fresh()->read_at);
        $this->postJson(route('interpresso.notifications.read', $own->id), ['read' => false])->assertOk()->assertJsonCount(1, 'notifications');
        $this->assertNull($own->fresh()->read_at);
        $this->postJson(route('interpresso.notifications.read-all'))->assertOk()->assertJsonCount(0, 'notifications');
        $this->assertNull($foreign->fresh()->read_at);
        $this->assertNotNull($own->fresh()->read_at);
    }

    #[Test]
    public function login_locks_out_after_ten_attempts_with_the_existing_message(): void
    {
        RateLimiter::clear('interpresso:login:127.0.0.1');
        $admin = Translator::firstOrFail();
        $before = $admin->getAttributes();
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post(route('interpresso.login.submit'), ['email' => $admin->email, 'password' => 'incorrect'])
                ->assertSessionHasErrors(['email' => 'Email or password are invalid.']);
        }
        $this->post(route('interpresso.login.submit'), ['email' => $admin->email, 'password' => 'aaaaaaaa'])
            ->assertSessionHasErrors('email')
            ->assertSessionHas('errors', fn ($errors) => preg_match('/^Slow down! Please wait another \d+ seconds to log in\.$/', $errors->first('email')) === 1);
        $this->assertGuest(config('interpresso.translator_guard'));
        $this->assertSame($before, $admin->fresh()->getAttributes());
        $this->travel(61)->seconds();
        $this->post(route('interpresso.login.submit'), ['email' => $admin->email, 'password' => 'aaaaaaaa', 'remember' => '1'])->assertRedirect(route('interpresso.languages'));
        $this->assertAuthenticatedAs($admin, config('interpresso.translator_guard'));
        $this->post(route('interpresso.logout'))->assertRedirect(route('interpresso.login'));
        $this->assertGuest(config('interpresso.translator_guard'));
    }
}
