<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Notifications\TranslatorPasswordLink;
use AnyMedia\Interpresso\Services\TranslatorPasswords;
use AnyMedia\Interpresso\Tests\BaseTestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PasswordResetTest extends BaseTestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['queue.connections.database.connection' => 'testbench']);
    }

    private function passwords(): TranslatorPasswords
    {
        return resolve(TranslatorPasswords::class);
    }

    private function work(): void
    {
        $queue = Queue::connection('database');
        while ($job = $queue->pop(config('interpresso.queue_name'))) {
            $job->fire();
            $job->delete();
        }
    }

    private function credentials(Translator $translator, string $token): array
    {
        return ['email' => $translator->email, 'token' => $token, 'password' => 'account-holder-password', 'password_confirmation' => 'account-holder-password'];
    }

    #[Test]
    public function existing_and_unknown_addresses_get_identical_responses_and_comparable_timing_with_no_account_lookup_or_mail_in_http(): void
    {
        $translator = Translator::firstOrFail();
        $this->get(route('interpresso.password.request'))->assertOk(); // Warm the session and views.
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void { $queries[] = $query->sql; });
        $bodies = $pages = $durations = [];
        foreach ([$translator->email, 'missing@example.test', $translator->email, 'missing@example.test'] as $email) {
            $start = hrtime(true);
            $response = $this->post(route('interpresso.password.email'), ['email' => $email]);
            $durations[] = (hrtime(true) - $start) / 1e9;
            $response->assertStatus(302)->assertRedirect(route('interpresso.password.request'))
                ->assertSessionHas('password_status', __('interpresso::passwords.request_sent'))->assertSessionHasNoErrors();
            $bodies[] = $response->getContent();
            $pages[] = $this->get(route('interpresso.password.request'))->assertOk()->getContent();
        }
        $this->assertCount(1, array_unique($bodies), 'Redirect response bodies must match exactly.');
        $this->assertCount(1, array_unique($pages), 'Rendered responses must match exactly, without echoing the email.');
        $this->assertLessThan(0.15, abs(($durations[0] + $durations[2] - $durations[1] - $durations[3]) / 2), 'Existing/unknown request timing must be comparable.');
        foreach ($queries as $query) {
            $this->assertStringNotContainsString(config('interpresso.table_translators'), $query, 'Account lookup must happen only in the worker.');
        }
        Notification::assertNothingSent();
        $this->assertSame(4, Queue::connection('database')->size(config('interpresso.queue_name')));
        $this->work();
        Notification::assertSentToTimes($translator, TranslatorPasswordLink::class, 1);
        Notification::assertCount(1); // Unknown accounts and per-account cooldown send nothing.
        $notification = Notification::sent($translator, TranslatorPasswordLink::class)->first();
        $this->assertFalse($notification->invitation);
        $hash = DB::table(config('interpresso.table_password_reset_tokens'))->value('token');
        $this->assertNotSame($notification->token, $hash);
        $this->assertTrue(Hash::check($notification->token, $hash));
    }

    #[Test]
    public function reset_tokens_work_once_and_rotate_the_remember_token(): void
    {
        $translator = Translator::firstOrFail();
        $translator->setRememberToken('old-remember-token');
        $translator->save();
        $token = $this->passwords()->broker()->createToken($translator);
        $form = route('interpresso.password.reset', ['token' => $token, 'email' => $translator->email]);
        $this->get($form)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->from($form)->post(route('interpresso.password.update'), $this->credentials($translator, $token))
            ->assertRedirect(route('interpresso.login'))->assertSessionHasNoErrors();
        $hash = $translator->fresh()->password;
        $this->assertTrue(Hash::check('account-holder-password', $hash));
        $this->assertNotSame('old-remember-token', $translator->fresh()->getRememberToken());
        $this->assertDatabaseCount(config('interpresso.table_password_reset_tokens'), 0);
        $this->from($form)->post(route('interpresso.password.update'), $this->credentials($translator, $token))
            ->assertRedirect($form)->assertSessionHasErrors(['email' => __('interpresso::passwords.invalid_token')]);
        $this->assertSame($hash, $translator->fresh()->password);
    }

    #[Test]
    public function configured_expiry_is_enforced(): void
    {
        config(['interpresso.password_reset.expire' => 2, 'auth.passwords.interpresso_translators.expire' => 2]);
        $translator = Translator::firstOrFail();
        $hash = $translator->password;
        $token = $this->passwords()->broker()->createToken($translator);
        $this->assertStringContainsString('2 minutes', implode(' ', (new TranslatorPasswordLink($token))->toMail($translator)->outroLines));
        $this->travel(121)->seconds();
        $this->post(route('interpresso.password.update'), $this->credentials($translator, $token))->assertSessionHasErrors('email');
        $this->assertSame($hash, $translator->fresh()->password);
    }

    #[Test]
    public function a_token_cannot_reset_another_translator(): void
    {
        $owner = Translator::firstOrFail();
        $other = $this->createUser(Language::all());
        $hash = $other->password;
        $token = $this->passwords()->broker()->createToken($owner);
        $this->post(route('interpresso.password.update'), $this->credentials($other, $token))->assertSessionHasErrors('email');
        $this->assertSame($hash, $other->fresh()->password);
        $this->assertTrue($this->passwords()->broker()->tokenExists($owner, $token));
    }

    #[Test]
    public function requests_are_limited_per_ip_even_when_addresses_change(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('interpresso.password.email'), ['email' => "unknown-$attempt@example.test"])->assertRedirect();
        }
        $this->post(route('interpresso.password.email'), ['email' => Translator::firstOrFail()->email])
            ->assertStatus(429)->assertHeader('Retry-After')->assertSee('Please wait');
        $this->assertSame(5, Queue::connection('database')->size(config('interpresso.queue_name')));
        $this->travel(61)->seconds();
        $this->post(route('interpresso.password.email'), ['email' => 'unknown@example.test'])->assertStatus(302);
    }

    #[Test]
    public function creating_and_resending_an_invitation_lets_only_the_recipient_set_a_password_and_log_in(): void
    {
        $admin = Translator::firstOrFail();
        $this->actingAs($admin)->post(route('interpresso.translators.store'), [
            'email' => 'invited@example.test', 'first_name' => 'New', 'last_name' => 'Translator', 'admin' => '0',
            'languages' => Language::pluck('id')->all(), 'password' => 'admin-injected-password', 'new_password' => 'also-ignored',
        ])->assertSessionHasNoErrors()->assertRedirect(route('interpresso.translators'));
        $translator = Translator::where('email', 'invited@example.test')->firstOrFail();
        $this->assertNull($translator->password);
        $first = Notification::sent($translator, TranslatorPasswordLink::class)->sole();
        $this->assertTrue($first->invitation);
        $this->assertSame('You are invited to Interpresso', $first->toMail($translator)->subject);
        $this->get(route('interpresso.translators.edit', $translator))->assertSee('Resend invitation')->assertDontSee('type="password"', false);
        $this->post(route('interpresso.translators.invite', $translator))->assertRedirect()->assertSessionHasNoErrors();
        $second = Notification::sent($translator, TranslatorPasswordLink::class)->last();
        $this->assertNotSame($first->token, $second->token);
        $this->assertFalse($this->passwords()->broker()->tokenExists($translator, $first->token));
        $this->assertNull($translator->fresh()->password);
        $this->post(route('interpresso.logout'));
        $this->post(route('interpresso.login.submit'), ['email' => $translator->email, 'password' => 'admin-injected-password'])->assertSessionHasErrors('email');
        $this->get($second->url($translator))->assertOk()->assertSee('Set password');
        $this->from($second->url($translator))->post(route('interpresso.password.update'), $this->credentials($translator, $second->token))
            ->assertRedirect(route('interpresso.login'))->assertSessionHasNoErrors();
        $this->assertGuest(config('interpresso.translator_guard'));
        $this->post(route('interpresso.login.submit'), ['email' => $translator->email, 'password' => 'account-holder-password'])
            ->assertRedirect(route('interpresso.languages'))->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($translator, config('interpresso.translator_guard'));
        $this->actingAs($admin)->post(route('interpresso.translators.invite', $translator))->assertForbidden();
    }

    #[Test]
    public function there_is_no_administrative_password_route_form_or_request(): void
    {
        $admin = Translator::firstOrFail();
        $this->assertFalse(Route::has('interpresso.translators.password'));
        $this->assertFalse(class_exists('AnyMedia\\Interpresso\\Requests\\UpdateTranslatorPasswordRequest'));
        $this->assertFalse(method_exists(\AnyMedia\Interpresso\Controllers\TranslatorController::class, 'updateNewPassword'));
        $this->actingAs($admin);
        foreach ([route('interpresso.translators', ['create' => 1]), route('interpresso.translators.edit', ['translator' => $admin, 'password' => 1])] as $url) {
            $this->get($url)->assertOk()->assertDontSee('type="password"', false)->assertDontSee('Update Password');
        }
        $before = $admin->password;
        $this->post('/translator/translators/' . $admin->id . '/password', ['new_password' => 'injected-password'])->assertNotFound();
        $this->post(route('interpresso.password.update'), ['email' => $admin->email, 'password' => 'injected-password', 'password_confirmation' => 'injected-password'])->assertSessionHasErrors('token');
        $this->assertSame($before, $admin->fresh()->password);
    }

    #[Test]
    public function host_users_and_tokens_are_never_used_even_with_the_same_email(): void
    {
        Schema::create('users', function (Blueprint $table): void { $table->id(); $table->string('email'); $table->string('password'); });
        Schema::create('password_reset_tokens', function (Blueprint $table): void { $table->string('email'); $table->string('token'); $table->timestamp('created_at'); });
        $translator = Translator::firstOrFail();
        $hostHash = Hash::make('host-password');
        DB::table('users')->insert(['email' => $translator->email, 'password' => $hostHash]);
        DB::table('password_reset_tokens')->insert(['email' => $translator->email, 'token' => Hash::make('host-token'), 'created_at' => now()]);
        $snapshot = DB::table('password_reset_tokens')->first();
        $token = $this->passwords()->broker()->createToken($translator);
        $this->post(route('interpresso.password.update'), $this->credentials($translator, $token))->assertSessionHasNoErrors();
        $this->assertSame($hostHash, DB::table('users')->value('password'));
        $this->assertEquals($snapshot, DB::table('password_reset_tokens')->first());
        $this->assertSame('interpresso_translators', config('auth.guards.' . config('interpresso.translator_guard') . '.provider'));
    }

    #[Test]
    public function changing_email_or_deleting_an_account_revokes_its_links(): void
    {
        $admin = Translator::firstOrFail();
        $translator = $this->createUser(Language::all());
        $token = $this->passwords()->broker()->createToken($translator);
        $this->actingAs($admin)->post(route('interpresso.translators.update', $translator), [
            'email' => 'changed@example.test', 'first_name' => 'Changed', 'last_name' => 'Translator', 'languages' => Language::pluck('id')->all(),
        ])->assertSessionHasNoErrors();
        $this->assertFalse($this->passwords()->broker()->tokenExists($translator, $token));
        $translator->refresh();
        $this->passwords()->broker()->createToken($translator);
        $this->post(route('interpresso.translators.delete', $translator))->assertRedirect();
        $this->assertDatabaseCount(config('interpresso.table_password_reset_tokens'), 0);
    }

    #[Test]
    public function mail_failure_preserves_an_onboarding_account_and_allows_resending(): void
    {
        Notification::swap(new class extends \Illuminate\Support\Testing\Fakes\NotificationFake {
            public function send($notifiables, $notification) { throw new \RuntimeException('Test SMTP unavailable'); }
        });
        $this->actingAs(Translator::firstOrFail())->post(route('interpresso.translators.store'), [
            'email' => 'mail-failure@example.test', 'first_name' => 'Mail', 'last_name' => 'Failure', 'admin' => '1',
        ])->assertRedirect()->assertSessionHas('toast.message', __('interpresso::passwords.invitation_failed'));
        $translator = Translator::where('email', 'mail-failure@example.test')->firstOrFail();
        $this->assertNull($translator->password);
        Notification::fake();
        $this->post(route('interpresso.translators.invite', $translator))->assertSessionHas('toast.message', __('interpresso::passwords.invitation_sent', ['email' => $translator->email]));
        Notification::assertSentTo($translator, TranslatorPasswordLink::class);
    }

    #[Test]
    public function unsafe_queue_configuration_cannot_reintroduce_a_timing_oracle(): void
    {
        config(['interpresso.password_reset.queue_connection' => 'sync']);
        foreach ([Translator::firstOrFail()->email, 'absent@example.test'] as $email) {
            $this->post(route('interpresso.password.email'), ['email' => $email])->assertStatus(302)
                ->assertSessionHas('password_status', __('interpresso::passwords.request_sent'));
        }
        Notification::assertNothingSent();
    }

    #[Test]
    public function invalid_reset_tokens_do_not_reveal_accounts_through_status_messages_or_timing(): void
    {
        $translator = Translator::firstOrFail();
        $bodies = $times = [];
        foreach ([$translator->email, 'missing@example.test'] as $email) {
            $start = hrtime(true);
            $response = $this->from(route('interpresso.password.request'))->post(route('interpresso.password.update'), [
                ...$this->credentials($translator, str_repeat('a', 64)), 'email' => $email,
            ])->assertStatus(302)->assertSessionHasErrors(['email' => __('interpresso::passwords.invalid_token')]);
            $times[] = (hrtime(true) - $start) / 1e9;
            $bodies[] = $response->getContent();
        }
        $this->assertSame($bodies[0], $bodies[1]);
        $this->assertLessThan(0.15, abs($times[0] - $times[1]));
    }

    #[Test]
    public function smtp_latency_is_confined_to_the_worker(): void
    {
        $translator = Translator::firstOrFail();
        $sent = 0;
        $this->app['events']->listen(\Illuminate\Notifications\Events\NotificationSending::class, function () use (&$sent): void {
            $sent++;
            usleep(300000); // Simulate a slow mail transport.
        });
        $this->app->forgetInstance(\Illuminate\Notifications\ChannelManager::class);
        Notification::swap($this->app->make(\Illuminate\Notifications\ChannelManager::class));
        config(['mail.default' => 'array']);
        $this->post(route('interpresso.password.email'), ['email' => $translator->email])->assertStatus(302);
        $this->post(route('interpresso.password.email'), ['email' => 'missing@example.test'])->assertStatus(302);
        $this->assertSame(0, $sent, 'Neither response may wait on the mail transport.');
        $this->work();
        $this->assertSame(1, $sent);
    }

    #[Test]
    public function mail_links_use_the_configured_origin_instead_of_the_request_host(): void
    {
        config(['interpresso.main_server_domain' => 'https://translations.example.test']);
        $translator = Translator::firstOrFail();
        $this->withServerVariables(['HTTP_HOST' => 'attacker.example.test'])->get('/translator/forgot-password')->assertOk();
        $url = (new TranslatorPasswordLink(str_repeat('a', 64)))->url($translator);
        $this->assertStringStartsWith('https://translations.example.test/translator/reset-password/', $url);
        $this->assertStringNotContainsString('attacker.example.test', $url);
    }

    #[Test]
    #[DataProvider('locales')]
    public function password_forms_and_mail_use_each_available_locale(string $locale): void
    {
        config(['interpresso.locale' => $locale]);
        $translator = Translator::firstOrFail();
        $translator->update(['locale' => $locale, 'password' => null]);
        $this->get(route('interpresso.login'))->assertSee(__('interpresso::passwords.forgot', [], $locale));
        $this->get(route('interpresso.password.request'))->assertDontSee('interpresso::')->assertSee(__('interpresso::passwords.send_link', [], $locale));
        $this->passwords()->sendLink($translator->email, 'en', true);
        $notification = Notification::sent($translator, TranslatorPasswordLink::class)->sole();
        $this->assertSame($locale, $notification->locale);
        app('translator')->setLocale($locale);
        $message = $notification->toMail($translator);
        $this->assertSame(__('interpresso::passwords.invite_subject'), $message->subject);
        $html = view($message->view, $message->data())->render();
        $this->assertStringNotContainsString('interpresso::', $html);
        $this->assertStringContainsString(e(__('interpresso::passwords.invite_intro')), $html);
    }

    public static function locales(): array
    {
        return array_map(fn ($locale) => [$locale], ['en', 'de', 'fr', 'es', 'it']);
    }
}
