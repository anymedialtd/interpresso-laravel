<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
        Notification::fake();
    }

    private function profile(array $overrides = []): array
    {
        return array_replace([
            'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.test', 'phone' => '234234234',
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
        $this->assertNull($translator->password);
        Notification::assertSentTo($translator, \AnyMedia\Interpresso\Notifications\TranslatorPasswordLink::class, fn ($notification) => $notification->invitation);
        $this->assertSame(Language::pluck('id')->all(), $translator->languages->modelKeys());
        $this->get(route('interpresso.translators'))->assertSee($translator->email);
        $this->assertSame(2, Translator::count());
    }

    #[Test]
    public function invalid_user_data_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('interpresso.translators.store'), $this->profile([
            'email' => 'john', 'languages' => [],
        ]))->assertSessionHasErrors(['email', 'languages']);
        $this->assertSame(1, Translator::count());
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
        $this->actingAs($this->admin)->post(route('interpresso.translators.update', $translator), $this->profile(['languages' => [$german->id], 'password' => 'injected-password', 'password_confirmation' => 'injected-password', 'new_password' => 'injected-password']))->assertSessionHasNoErrors();
        $this->assertSame($hash, $translator->fresh()->password);
        $this->assertSame([$german->id], $translator->fresh()->languages->modelKeys());
        $before = $translator->fresh()->getAttributes();
        $this->post(route('interpresso.translators.update', $translator), $this->profile(['languages' => [99999]]))->assertSessionHasErrors("languages.0");
        $this->assertSame($before, $translator->fresh()->getAttributes());
    }

    #[Test]
    public function admins_can_create_set_and_clear_interface_locales_without_changing_other_profile_fields(): void
    {
        $this->actingAs($this->admin)->post(route('interpresso.translators.store'), $this->profile(['locale' => 'it']))->assertSessionHasNoErrors();
        $translator = Translator::where('email', 'john@example.test')->firstOrFail();
        $this->assertSame('it', $translator->locale);
        $form = route('interpresso.translators.edit', $translator);
        $this->get($form)->assertOk()->assertSee('id="field-locale"', false)->assertSee('Italiano');
        $this->post(route('interpresso.translators.update', $translator), $this->profile(['locale' => 'fr']))->assertSessionHasNoErrors();
        $this->assertSame('fr', $translator->fresh()->locale);

        $before = $translator->fresh()->getAttributes();
        $this->from($form)->post(route('interpresso.translators.update', $translator), $this->profile(['locale' => '../de']))
            ->assertRedirect($form)->assertSessionHasErrors('locale');
        $this->assertSame($before, $translator->fresh()->getAttributes());
        $response = $this->get($form)->assertOk()->assertSee('Please select an available interface language.')->assertDontSee('interpresso::');
        $this->assertSame(1, substr_count($response->getContent(), 'Please select an available interface language.'));

        // Older clients that omit the optional field must preserve the saved value.
        $this->post(route('interpresso.translators.update', $translator), $this->profile())->assertSessionHasNoErrors();
        $this->assertSame('fr', $translator->fresh()->locale);
        $this->post(route('interpresso.translators.update', $translator), $this->profile(['locale' => '']))->assertSessionHasNoErrors();
        $this->assertNull($translator->fresh()->locale);
        $this->assertSame($before['password'], $translator->fresh()->password);
        $this->assertNull($this->admin->fresh()->locale);
    }

    #[Test]
    public function non_admins_cannot_change_someone_elses_interface_locale(): void
    {
        $translator = $this->createUser(Language::all(), ['locale' => 'de']);
        $this->actingAs($translator)->post(route('interpresso.translators.update', $this->admin), $this->profile(['locale' => 'fr']))->assertForbidden();
        $this->assertNull($this->admin->fresh()->locale);
        $this->assertSame('de', $translator->fresh()->locale);
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
