<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Tests\BaseTestCase;

class SecurityHeadersTest extends BaseTestCase
{
    use RefreshDatabase;

    #[Test]
    public function package_pages_send_a_strict_policy_and_security_headers(): void
    {
        $response = $this->get(route('interpresso.login'))->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), fullscreen=()');
        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertSame("default-src 'none'; script-src 'self'; script-src-attr 'none'; style-src 'self'; style-src-attr 'none'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'", $policy);
        $this->assertStringNotContainsString('unsafe-inline', $policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);

        $this->get(route('interpresso.languages'))->assertRedirect(route('interpresso.login'))->assertHeader('Content-Security-Policy', $policy);
    }

    #[Test]
    public function authenticated_pages_json_and_errors_keep_the_policy(): void
    {
        $policy = $this->get(route('interpresso.login'))->assertOk()->headers->get('Content-Security-Policy');
        $this->actingAs(Translator::firstOrFail(), config('interpresso.translator_guard'));
        foreach (['languages', 'translators', 'settings', 'manual'] as $screen) {
            $page = $this->get(route('interpresso.' . $screen));
            $this->assertSame(200, $page->status(), $screen);
            $page->assertHeader('Content-Security-Policy', $policy);
        }
        $this->get(route('interpresso.translations', Language::firstOrFail()))->assertOk()->assertHeader('Content-Security-Policy', $policy);
        $this->getJson(route('interpresso.notifications'))->assertOk()->assertHeader('Content-Security-Policy', $policy);
        $this->post(route('interpresso.settings.update', 'unknown'), [])->assertNotFound()->assertHeader('Content-Security-Policy', $policy);
    }

    #[Test]
    public function headers_can_be_disabled_by_configuration(): void
    {
        config(['interpresso.security_headers.enabled' => false]);
        $this->get(route('interpresso.login'))->assertOk()
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeaderMissing('X-Content-Type-Options')
            ->assertHeaderMissing('Referrer-Policy')
            ->assertHeaderMissing('Permissions-Policy');
    }

    #[Test]
    public function the_policy_does_not_leak_to_host_routes_or_package_api_routes(): void
    {
        Route::middleware('web')->get('/host-page', fn () => response('Host page'));
        // Even reusing the package session group must not opt a host route in.
        Route::middleware(config('interpresso.translator_guard'))->get('/host-session', fn () => response('Host session'));
        foreach (['/host-page', '/host-session', '/api/version'] as $url) {
            $this->get($url)->assertOk()->assertHeaderMissing('Content-Security-Policy')
                ->assertHeaderMissing('Permissions-Policy')->assertHeaderMissing('Referrer-Policy');
        }
    }

    #[Test]
    public function extra_sources_append_explicit_origins_without_replacing_the_base_policy(): void
    {
        config(['interpresso.security_headers.extra_sources' => [
            'script-src' => ['https://cdn.example.com', 'https://cdn.example.com'],
            'style-src' => ['https://cdn.example.com'],
            'connect-src' => ['http://localhost:8099'],
            'img-src' => [],
        ]]);
        $policy = $this->get(route('interpresso.login'))->assertOk()->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' https://cdn.example.com;", $policy);
        $this->assertStringContainsString("style-src 'self' https://cdn.example.com;", $policy);
        $this->assertStringContainsString("connect-src 'self' http://localhost:8099;", $policy);
        $this->assertStringContainsString("img-src 'self' data:;", $policy);
        $this->assertStringContainsString("default-src 'none';", $policy);
        $this->assertStringContainsString("frame-ancestors 'none';", $policy);
    }

    #[Test]
    #[DataProvider('invalidConfigurations')]
    public function invalid_configuration_is_rejected_explicitly(string $key, mixed $value): void
    {
        $this->withoutExceptionHandling();
        config(['interpresso.security_headers.' . $key => $value]);
        $this->expectException(InvalidArgumentException::class);
        $this->get(route('interpresso.login'));
    }

    public static function invalidConfigurations(): array
    {
        $cases = [
            'non-boolean enable' => ['enabled', 'false'],
            'null enable' => ['enabled', null],
            'empty policy string' => ['extra_sources', ''],
            'null sources' => ['extra_sources', null],
            'unknown directive' => ['extra_sources', ['script-src-elem' => ['https://cdn.example.com']]],
            'base override' => ['extra_sources', ['default-src' => ['https://cdn.example.com']]],
            'framing override' => ['extra_sources', ['frame-ancestors' => ['https://cdn.example.com']]],
            'scalar sources' => ['extra_sources', ['script-src' => 'https://cdn.example.com']],
            'associative sources' => ['extra_sources', ['script-src' => ['cdn' => 'https://cdn.example.com']]],
        ];
        foreach (['', '*', 'https://*.example.com', 'https:', 'data:', 'blob:', "'unsafe-inline'", "'unsafe-eval'", "'none'", "'nonce-test'", 'https://cdn.example.com;script-src *', "https://cdn.example.com\r\nX-Test: injected", 'https://user:pass@cdn.example.com', 'https://cdn.example.com/path', 'https://cdn.example.com?query', 'https://cdn.example.com#fragment', 'https://cdn.example.com:99999', 123, null] as $index => $source) {
            $cases['invalid source ' . $index] = ['extra_sources', ['script-src' => [$source]]];
        }
        return $cases;
    }
}
