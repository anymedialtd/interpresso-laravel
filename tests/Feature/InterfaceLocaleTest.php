<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use AnyMedia\Interpresso\Middleware\SetInterfaceLocale;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\InterfaceLocales;
use AnyMedia\Interpresso\Tests\BaseTestCase;
use Illuminate\Contracts\Translation\Translator as Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class InterfaceLocaleTest extends BaseTestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_translator_can_switch_and_keep_their_locale_through_logout_and_a_new_login(): void
    {
        $translator = $this->createUser(Language::all(), ['locale' => 'de', 'password' => Hash::make('locale-password')]);
        config(['app.locale' => 'es', 'interpresso.locale' => 'it']);
        $this->withCookie('interpresso-locale', 'en')->actingAs($translator, config('interpresso.translator_guard'));
        $this->get(route('interpresso.languages'))->assertOk()->assertSee('<html lang="de"', false)->assertSee('Sprachen');

        $response = $this->from(route('interpresso.languages'))->post(route('interpresso.locale.update'), ['locale' => 'fr', 'admin' => 1]);
        $response->assertRedirect(route('interpresso.languages'))->assertSessionHasNoErrors()->assertCookie('interpresso-locale', 'fr');
        $this->assertSame('fr', $translator->fresh()->locale);
        $this->assertFalse($translator->fresh()->admin);
        $this->get(route('interpresso.languages'))->assertOk()->assertSee('<html lang="fr"', false)->assertSee('Langues');
        $this->post(route('interpresso.logout'))->assertRedirect(route('interpresso.login'));
        $this->assertGuest(config('interpresso.translator_guard'));

        // A different browser cookie proves the record, rather than the cookie,
        // restores the preference after a real credential-based login.
        Auth::forgetGuards();
        $this->withCookie('interpresso-locale', 'en')->post(route('interpresso.login.submit'), [
            'email' => $translator->email, 'password' => 'locale-password',
        ])->assertRedirect(route('interpresso.languages'));
        $this->get(route('interpresso.languages'))->assertOk()->assertSee('<html lang="fr"', false)->assertSee('Langues')->assertDontSee('interpresso::');
        $this->assertSame('es', config('app.locale'));
    }

    #[Test]
    public function a_guest_can_switch_the_login_page_using_the_encrypted_cookie(): void
    {
        $login = route('interpresso.login');
        $this->get($login)->assertOk()->assertSee('<html lang="en"', false);
        $response = $this->from($login)->post(route('interpresso.locale.update'), ['locale' => 'de']);
        $response->assertRedirect($login)->assertCookie('interpresso-locale', 'de')->assertSessionHasNoErrors();
        $cookie = $response->getCookie('interpresso-locale');
        $this->assertSame('/translator', $cookie->getPath());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertGreaterThan(time() + 3600 * 24 * 360, $cookie->getExpiresTime());
        $this->assertNull(Translator::firstOrFail()->locale);

        // Send the actual response cookie, already encrypted by package middleware.
        $this->withUnencryptedCookie('interpresso-locale', $response->getCookie('interpresso-locale', false)->getValue());
        $this->get($login)->assertOk()->assertSee('<html lang="de"', false)->assertSee('Anmelden')->assertDontSee('interpresso::');
        $this->get($login)->assertOk()->assertSee('<html lang="de"', false);
    }

    #[Test]
    #[DataProvider('preferences')]
    public function preferences_are_resolved_in_order_and_invalid_values_are_ignored(?string $saved, ?string $cookie, mixed $package, mixed $host, string $expected): void
    {
        // Start with a booted translator before overriding request-time config;
        // Laravel itself cannot construct a translator with a path as its locale.
        app('translator');
        config(['interpresso.locale' => $package, 'app.locale' => $host]);
        if ($saved !== null) {
            $this->actingAs($this->createUser(Language::all(), ['locale' => $saved]), config('interpresso.translator_guard'));
        }
        if ($cookie !== null) {
            $this->withCookie('interpresso-locale', $cookie);
        }
        $this->get(route($saved !== null ? 'interpresso.languages' : 'interpresso.login'))->assertOk()
            ->assertSee('<html lang="' . $expected . '"', false)->assertDontSee('interpresso::');
        $this->assertSame($host, config('app.locale'));
    }

    public static function preferences(): array
    {
        return [
            'record first' => ['de', 'fr', 'it', 'es', 'de'],
            'invalid record' => ['unknown', 'fr', 'it', 'es', 'fr'],
            'cookie before package' => [null, 'fr', 'it', 'es', 'fr'],
            'invalid cookie' => [null, '../de', 'it', 'es', 'it'],
            'package before host' => [null, null, 'it', 'es', 'it'],
            'host default' => [null, null, null, 'es', 'es'],
            'invalid package' => [null, null, 'unknown', 'es', 'es'],
            'non-string package' => [null, null, ['de'], 'es', 'es'],
            'unsupported host' => [null, null, null, 'nl', 'en'],
            'invalid host' => [null, null, '../de', '../de', 'en'],
        ];
    }

    #[Test]
    #[DataProvider('invalidLocales')]
    public function an_invalid_switch_changes_neither_the_profile_nor_the_cookie(mixed $invalid): void
    {
        $translator = $this->createUser(Language::all(), ['locale' => 'de']);
        $this->actingAs($translator)->from(route('interpresso.languages'))
            ->post(route('interpresso.locale.update'), ['locale' => $invalid])->assertRedirect(route('interpresso.languages'))
            ->assertSessionHasErrors('locale', null, 'interfaceLocale')->assertCookieMissing('interpresso-locale');
        $this->assertSame('de', $translator->fresh()->locale);
        $this->get(route('interpresso.languages'))->assertOk()->assertSee('<html lang="de"', false)->assertDontSee('interpresso::');
    }

    #[Test]
    #[DataProvider('invalidLocales')]
    public function invalid_preferences_never_reach_either_locale_setter(mixed $invalid): void
    {
        config(['interpresso.locale' => $invalid, 'app.locale' => 'en']);
        $translation = Mockery::mock(Translation::class);
        $translation->shouldReceive('getLocale')->once()->andReturn('en');
        // Any call containing unvalidated input fails this strict expectation.
        $translation->shouldReceive('setLocale')->with('en')->twice();
        $application = Mockery::mock($this->app)->makePartial();
        $application->shouldNotReceive('setLocale');
        App::swap($application);
        try {
            $middleware = new SetInterfaceLocale(resolve(InterfaceLocales::class), $translation);
            $response = $middleware->handle(Request::create('/translator/login', 'GET', [], ['interpresso-locale' => $invalid]), fn () => response('safe'));
            $this->assertSame('safe', $response->getContent());
        } finally {
            App::swap($this->app);
        }
    }

    public static function invalidLocales(): array
    {
        return [['unknown'], ['../de'], ['"><script>alert(1)</script>'], [['de']], [23], [''], [null]];
    }

    #[Test]
    public function the_interface_locale_covers_package_json_and_manual_routes_and_restores_the_host_translator(): void
    {
        config(['app.locale' => 'es', 'interpresso.locale' => 'de']);
        Route::middleware(config('interpresso.translator_guard'))->get('/translator/locale-probe', fn () => response()->json([
            'locale' => app('translator')->getLocale(), 'source' => config('app.locale'),
        ]));
        Route::get('/host-locale-probe', fn () => response()->json(['locale' => app('translator')->getLocale()]));
        $previous = app('translator')->getLocale();
        $this->getJson('/translator/locale-probe')->assertOk()->assertExactJson(['locale' => 'de', 'source' => 'es']);
        $this->getJson('/host-locale-probe')->assertOk()->assertExactJson(['locale' => $previous]);
        $this->actingAs($this->createUser(Language::all(), ['locale' => 'fr']))
            ->get(route('interpresso.manual'))->assertOk()->assertSee('Premiers pas')->assertSee('<html lang="fr"', false);
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->getName() ?? '', 'interpresso.')) {
                $this->assertContains(SetInterfaceLocale::class, app('router')->gatherRouteMiddleware($route), $route->getName());
            }
        }
    }

    #[Test]
    public function language_switches_require_csrf_and_get_cannot_mutate_preferences(): void
    {
        // Enable Laravel's real CSRF check instead of the Testbench bypass.
        $this->app['env'] = 'production';
        try {
            $this->post(route('interpresso.locale.update'), ['locale' => 'de'])->assertStatus(419)->assertCookieMissing('interpresso-locale');
            $this->get(route('interpresso.locale.update', ['locale' => 'de']))->assertStatus(405);
            $this->assertNull(Translator::firstOrFail()->locale);
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}
