<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Tests\BaseTestCase;

class CspViewsTest extends BaseTestCase
{
    use RefreshDatabase;

    #[Test]
    #[DataProvider('themes')]
    public function cookies_render_the_theme_without_javascript(string $cookie, ?string $expected): void
    {
        $this->withUnencryptedCookie('interpresso-color-theme', $cookie);
        $response = $this->get(route('interpresso.login'))->assertOk();
        $document = $this->document($response->getContent());
        $html = $document->documentElement;
        $this->assertSame($expected ?? '', $html->getAttribute('data-theme'));
        $this->assertSame($expected === 'dark' ? 'dark' : '', $html->getAttribute('class'));
        $this->assertSame('/translator', $html->getAttribute('data-theme-cookie-path'));
        $this->assertFalse(app(\Illuminate\Cookie\Middleware\EncryptCookies::class)->isDisabled('interpresso-color-theme'));
        $this->assertFalse(app(\AnyMedia\Interpresso\Middleware\EncryptCookies::class)->isDisabled(config('session.cookie')));
    }

    public static function themes(): array
    {
        return [['dark', 'dark'], ['light', 'light'], ['', null], ['invalid', null], ['"><script>alert(1)</script>', null]];
    }

    #[Test]
    public function a_new_visitor_leaves_the_first_paint_theme_to_the_stylesheet(): void
    {
        $response = $this->get(route('interpresso.login'))->assertOk();
        $this->assertFalse($this->document($response->getContent())->documentElement->hasAttribute('data-theme'));
    }

    #[Test]
    public function toast_json_round_trips_as_an_escaped_attribute(): void
    {
        $toast = ['message' => '" onmouseover="alert(1)"><script>alert("x")</script> & <style>body{display:none}</style>', 'type' => 'WARNING', 'duration' => 4000];
        $this->get(route('interpresso.login'))->assertOk();
        $response = $this->withSession(['toast' => $toast])->get(route('interpresso.login'))->assertOk();
        $document = $this->document($response->getContent());
        $data = $document->getElementById('toast-data');
        $this->assertSame('div', $data->tagName);
        $this->assertTrue($data->hasAttribute('hidden'));
        $this->assertSame($toast, json_decode($data->getAttribute('data-toast'), true, 512, JSON_THROW_ON_ERROR));
        $this->assertNoInlineContent($document);
    }

    #[Test]
    public function every_screen_and_form_uses_external_scripts_and_styles_only(): void
    {
        $this->assertNoInlineContent($this->document($this->get(route('interpresso.login'))->assertOk()->getContent()));
        $admin = Translator::firstOrFail();
        $this->actingAs($admin);
        foreach ([
            route('interpresso.languages', ['create' => 1]),
            route('interpresso.translators', ['create' => 1]),
            route('interpresso.translators.edit', $admin),
            route('interpresso.translators.edit', ['translator' => $admin, 'password' => 1]),
            route('interpresso.translations', Language::firstOrFail()),
            route('interpresso.settings'),
            route('interpresso.manual'),
        ] as $url) {
            $document = $this->document($this->get($url)->assertOk()->getContent());
            $this->assertNoInlineContent($document);
        }
    }

    private function assertNoInlineContent(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);
        $this->assertSame(0, $xpath->query('//script[not(@src) or normalize-space(.) != ""] | //style | //@style')->length);
        $this->assertSame(0, $xpath->query('//@*[starts-with(translate(name(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "on")]')->length);
    }

    private function document(string $html): DOMDocument
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $document;
    }
}
