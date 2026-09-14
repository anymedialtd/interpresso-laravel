<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Notifications\PendingTranslationsNotification;
use AnyMedia\Interpresso\Tests\BaseTestCase;

class TranslatorTest extends BaseTestCase
{
    use RefreshDatabase;
    private Translator $admin;

    public function setUp(): void
    {
        parent::setUp();
        $this->admin = Translator::firstOrFail();
    }

    private function profile(array $overrides = []): array
    {
        return array_replace([
            'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.test', 'phone' => '234234234',
            'password' => 'aaaaaaaa', 'password_confirmation' => 'aaaaaaaa',
            'languages' => Language::pluck('id')->all(), 'admin' => '0',
        ], $overrides);
    }

    #[Test]
    public function admin_can_access_translators_and_open_the_create_form(): void
    {
        $this->actingAs($this->admin)->get(route('interpresso.translators'))->assertOk()
            ->assertSee(__('interpresso::translators.button_toggle_create_form'))->assertSee(__('interpresso::table.edit'))
            ->assertDontSee('id="createOrUpdateForm"', false);
        $this->get(route('interpresso.translators', ['create' => 1]))->assertOk()->assertSee('id="createOrUpdateForm"', false);
    }

    #[Test]
    public function guest_cannot_access_translators(): void
    {
        $this->get(route('interpresso.translators'))->assertRedirect(route('interpresso.login'));
    }

    #[Test]
    public function non_admin_cannot_access_translators(): void
    {
        $this->actingAs($this->createUser(Language::all()))->get(route('interpresso.translators'))->assertForbidden();
    }

    #[Test]
    public function admin_can_delete_user(): void
    {
        $translator = $this->createUser(Language::all());
        $this->actingAs($this->admin)->post(route('interpresso.translators.delete', $translator))->assertRedirect();
        $this->get(route('interpresso.translators'))->assertOk()->assertDontSee($translator->email)->assertSee($this->admin->email);
        $this->assertDatabaseMissing(config('interpresso.table_translators'), ['id' => $translator->id]);
    }

    #[Test]
    public function admin_cannot_delete_first_user(): void
    {
        $this->actingAs($this->admin)->post(route('interpresso.translators.delete', $this->admin))->assertForbidden();
        $this->assertSame(1, Translator::count());
    }

    #[Test]
    public function admin_can_create_a_user(): void
    {
        $this->actingAs($this->admin)->post(route('interpresso.translators.store'), $this->profile())->assertRedirect()->assertSessionHasNoErrors();
        $translator = Translator::where('email', 'john@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('aaaaaaaa', $translator->password));
        $this->assertSame(Language::pluck('id')->all(), $translator->languages->modelKeys());
        $this->get(route('interpresso.translators'))->assertSee($translator->email);
        $this->assertSame(2, Translator::count());
    }

    #[Test]
    public function invalid_user_data_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('interpresso.translators.store'), $this->profile([
            'email' => 'john', 'password_confirmation' => 'aaaaaaa', 'languages' => [],
        ]))->assertSessionHasErrors(['password_confirmation', 'email', 'languages']);
        $this->assertSame(1, Translator::count());
    }

    #[Test]
    public function admin_can_update_users_password(): void
    {
        $translator = $this->createUser(Language::all());
        $this->assertFalse(Auth::attempt(['email' => $translator->email, 'password' => 'newvalue']));
        $this->actingAs($this->admin)->post(route('interpresso.translators.password', $translator), [
            'new_password' => 'newvalue', 'new_password_confirmation' => 'newvalue',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Auth::attempt(['email' => $translator->email, 'password' => 'newvalue']));
    }

    #[Test]
    public function admin_can_search_translator(): void
    {
        $first = $this->createUser(Language::all());
        $second = $this->createUser(Language::all());
        $this->actingAs($this->admin)->get(route('interpresso.translators'))->assertSee($first->email)->assertSee($second->email);
        $this->get(route('interpresso.translators', ['search' => $this->admin->email]))->assertOk()
            ->assertSee($this->admin->email)->assertDontSee($first->email)->assertDontSee($second->email);
    }

    #[Test]
    public function profile_update_keeps_password_and_syncs_valid_language_ids(): void
    {
        $translator = $this->createUser(Language::all());
        $hash = $translator->password;
        $german = Language::create(['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch']);
        $this->actingAs($this->admin)->post(route('interpresso.translators.update', $translator), $this->profile(['languages' => [$german->id]]))->assertSessionHasNoErrors();
        $this->assertSame($hash, $translator->fresh()->password);
        $this->assertSame([$german->id], $translator->fresh()->languages->modelKeys());
        $before = $translator->fresh()->getAttributes();
        $this->post(route('interpresso.translators.update', $translator), $this->profile(['languages' => [99999]]))->assertSessionHasErrors("languages.0");
        $this->assertSame($before, $translator->fresh()->getAttributes());
    }

    #[Test]
    public function pending_notifications_are_sent_for_assigned_languages(): void
    {
        Notification::fake();
        $language = Language::firstOrFail();
        $language->translations()->create(['language_code' => $language->code, 'type' => 'json', 'key' => 'pending', 'shared_identifier' => 'json::pending', 'value' => '', 'needs_translation' => true]);
        Setting::firstOrFail()->update(['enable_pending_notifications' => true]);
        Setting::getFreshCached();
        $translator = $this->createUser(Language::all());
        $this->actingAs($this->admin)->post(route('interpresso.translators.notify', $translator))->assertRedirect();
        Notification::assertSentTo($translator, PendingTranslationsNotification::class);
    }
}
