<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\InterpressoTranslatorServiceProvider;
use AnyMedia\Interpresso\Tests\BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class LocalizedManualTest extends BaseTestCase
{
    use RefreshDatabase;

    public static function interfaceLocales(): array
    {
        return [
            'German UI' => ['de', 'Einstellungen'],
            'French UI' => ['fr', 'Paramètres'],
            'Spanish UI' => ['es', 'Configuración'],
            'Italian UI' => ['it', 'Impostazioni'],
        ];
    }

    #[Test]
    #[DataProvider('interfaceLocales')]
    public function vendor_import_keeps_the_localized_interface_available_with_reviewed_database_overrides(string $locale, string $heading): void
    {
        Setting::firstOrFail()->update(['db_loader' => true, 'import_vendor' => true]);
        Setting::getFreshCached();
        (new InterpressoTranslatorServiceProvider($this->app))->register();
        $this->app->setLocale($locale);
        $this->actingAs(Translator::firstOrFail(), config('interpresso.translator_guard'));
        $language = Language::firstOrCreate(['code' => $locale], ['name' => $locale, 'native_name' => $locale]);
        Translation::create([
            'language_id' => $language->id, 'language_code' => $locale, 'namespace' => 'interpresso',
            'type' => 'php', 'group' => 'navbar', 'key' => 'manual', 'shared_identifier' => 'interpresso::navbar.manual',
            'is_vendor' => true, 'value' => 'Unreviewed label', 'old_value' => 'Reviewed label', 'approved' => false,
            'needs_translation' => false,
        ]);
        $this->get(route('interpresso.settings'))->assertOk()->assertSee($heading)
            ->assertSee('Reviewed label')->assertDontSee('Unreviewed label')->assertDontSee('interpresso::');
    }

    public static function locales(): array
    {
        return [
            'German' => ['de', 'Erste Schritte'],
            'French' => ['fr', 'Premiers pas'],
            'Spanish' => ['es', 'Primeros pasos'],
            'Italian' => ['it', 'Primi passi'],
            'untranslated' => ['nl', 'Getting Started'],
            'English' => ['en', 'Getting Started'],
            'invalid filename' => ['../de', 'Getting Started'],
        ];
    }

    #[Test]
    #[DataProvider('locales')]
    public function the_manual_uses_the_active_locale_with_english_fallback_and_stable_anchors(string $locale, string $heading): void
    {
        $this->actingAs(Translator::firstOrFail(), config('interpresso.translator_guard'));
        $this->app->setLocale('en');
        $english = $this->get(route('interpresso.manual'))->assertOk();
        $englishSections = $english->viewData('manualSections');

        if ($locale === '../de') {
            // Laravel's translator rejects slashes. Exercise the controller's
            // filename guard even if application config was set directly.
            config(['app.locale' => $locale]);
        } else {
            $this->app->setLocale($locale);
        }
        $response = $this->get(route('interpresso.manual'))->assertOk();
        $html = $response->viewData('manualHtml');
        $this->assertStringContainsString('>' . $heading . '</h2>', $html);
        $sections = $response->viewData('manualSections');
        $this->assertSame(array_column($englishSections, 'id'), array_column($sections, 'id'));
        $this->assertSame(array_column($englishSections, 'level'), array_column($sections, 'level'));
        foreach ($sections as $section) {
            $this->assertSame(1, substr_count($html, 'id="' . $section['id'] . '"'), $locale . ': ' . $section['id']);
            $response->assertSee('href="#' . $section['id'] . '"', false);
        }
        $this->assertStringNotContainsString('{#', $html);
        if (in_array($locale, ['de', 'fr', 'es', 'it'], true)) {
            $this->assertStringNotContainsString('This is the manual displayed', $html);
            // CLI examples and all technical inline code must survive translation.
            $original = file_get_contents(dirname(__DIR__, 2) . '/docs/APPLICATION_MANUAL.md');
            $translated = file_get_contents(dirname(__DIR__, 2) . '/docs/APPLICATION_MANUAL.' . $locale . '.md');
            preg_match_all('/```[^\n]*\n.*?```|`[^`\n]+`/s', $original, $expectedCode);
            preg_match_all('/```[^\n]*\n.*?```|`[^`\n]+`/s', $translated, $actualCode);
            $this->assertSame($expectedCode[0], $actualCode[0], "$locale: a command, identifier or code example changed or is missing");
        } else {
            $this->assertSame($english->viewData('manualHtml'), $html);
        }
    }
}
