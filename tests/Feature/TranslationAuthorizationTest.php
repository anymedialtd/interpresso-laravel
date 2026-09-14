<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Tests\BaseTestCase;

class TranslationAuthorizationTest extends BaseTestCase
{
    use RefreshDatabase;
    private Language $permittedLanguage;
    private Language $forbiddenLanguage;
    private Translator $translator;
    private Translation $forbiddenTranslation;

    public function setUp(): void
    {
        parent::setUp();
        $this->permittedLanguage = Language::firstOrFail();
        $this->translator = $this->createUser(Language::all());
        $this->forbiddenLanguage = Language::create(['name' => 'German', 'native_name' => 'Deutsch', 'code' => 'de']);
        $this->forbiddenTranslation = $this->forbiddenLanguage->translations()->create([
            'language_code' => 'de', 'type' => 'json', 'key' => 'secret.key', 'shared_identifier' => 'json::secret.key',
            'value' => 'Geheim', 'old_value' => 'Secret', 'approved' => false, 'needs_translation' => false,
        ]);
    }

    public static function actions(): array
    {
        return array_map(fn ($action) => [$action], ['modal', 'suggest', 'update', 'update-all', 'approve', 'request', 'restore-request', 'restore']);
    }

    #[Test]
    #[DataProvider('actions')]
    public function a_translator_cannot_act_on_another_languages_row(string $action): void
    {
        $before = $this->forbiddenTranslation->fresh()->getAttributes();
        $this->actingAs($this->translator);
        foreach ([$this->permittedLanguage, $this->forbiddenLanguage] as $language) {
            $url = route('interpresso.translations.' . $action, ['language' => $language, 'id' => $this->forbiddenTranslation->id]);
            $response = $action === 'modal' ? $this->getJson($url) : $this->post($url, ['translatedValue' => 'Changed']);
            $response->assertForbidden();
            $this->assertSame($before, $this->forbiddenTranslation->fresh()->getAttributes());
        }
    }

    #[Test]
    #[DataProvider('actions')]
    public function even_an_admin_cannot_mix_a_language_with_another_languages_row(string $action): void
    {
        $before = $this->forbiddenTranslation->fresh()->getAttributes();
        $this->actingAs(Translator::firstOrFail());
        $url = route('interpresso.translations.' . $action, ['language' => $this->permittedLanguage, 'id' => $this->forbiddenTranslation->id]);
        ($action === 'modal' ? $this->getJson($url) : $this->post($url, ['translatedValue' => 'Changed']))->assertForbidden();
        $this->assertSame($before, $this->forbiddenTranslation->fresh()->getAttributes());
    }

    #[Test]
    public function a_translator_may_edit_their_own_language(): void
    {
        $own = $this->permittedLanguage->translations()->create([
            'language_code' => 'en', 'type' => 'json', 'key' => 'own.key', 'shared_identifier' => 'json::own.key', 'value' => 'Mine', 'needs_translation' => false,
        ]);
        $parameters = ['language' => $this->permittedLanguage, 'id' => $own->id];
        $this->actingAs($this->translator)->getJson(route('interpresso.translations.modal', $parameters))->assertOk()->assertJsonPath('value', 'Mine');
        $this->post(route('interpresso.translations.update', $parameters), ['translatedValue' => 'Updated'])->assertRedirect();
        $this->assertSame('Updated', $own->fresh()->value);
    }

    #[Test]
    public function modal_examples_and_fallback_do_not_expose_unassigned_languages(): void
    {
        $own = $this->permittedLanguage->translations()->create([
            'language_code' => 'en', 'type' => 'json', 'key' => 'secret.key', 'shared_identifier' => 'json::secret.key', 'value' => '', 'needs_translation' => false,
        ]);
        config()->set('app.fallback_locale', 'de');
        $parameters = ['language' => $this->permittedLanguage, 'id' => $own->id];
        $this->actingAs($this->translator)->getJson(route('interpresso.translations.modal', $parameters))
            ->assertOk()->assertJsonCount(1, 'examples')->assertJsonPath('example', null)->assertDontSee('Geheim');
        $this->getJson(route('interpresso.translations.modal', $parameters + ['example_language' => $this->forbiddenLanguage->id]))->assertForbidden();
    }
}
