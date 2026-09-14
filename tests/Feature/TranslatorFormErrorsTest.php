<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use AnyMedia\Interpresso\InterpressoTranslatorServiceProvider;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Tests\BaseTestCase;
use AnyMedia\Interpresso\TranslationLoader;

class TranslatorFormErrorsTest extends BaseTestCase
{
    use RefreshDatabase;

    private function useLoader(bool $database): void
    {
        File::delete(app()->langPath('en/validation.php'));
        Setting::firstOrFail()->update(['db_loader' => $database]);
        Setting::getFreshCached();
        // HTTP boots with an existing settings row. Testbench boots before its
        // in-memory migrations, so select the real saved loader after seeding.
        (new InterpressoTranslatorServiceProvider($this->app))->register();
        $this->app->forgetInstance('validator');
        $this->assertSame($database, app('translation.loader') instanceof TranslationLoader);
        $this->actingAs(Translator::firstOrFail());
    }

    #[Test]
    #[DataProvider('loaders')]
    public function create_errors_render_next_to_the_submitted_fields(bool $database): void
    {
        $this->useLoader($database);
        $form = route('interpresso.translators', ['create' => 1]);
        $this->from($form)->post(route('interpresso.translators.store'), [
            'email' => 'new@example.test', 'first_name' => 'Jamie', 'last_name' => 'Example',
            'admin' => '0',
        ])->assertRedirect($form)->assertSessionHasErrors(['languages']);
        $response = $this->get($form)->assertOk();
        $document = $this->document($response->getContent());
        $this->assertSame('new@example.test', $document->evaluate('string(//input[@name="email"]/@value)'));
        $this->assertSame('The languages field is required.', trim($document->evaluate('string(//form[@id="createOrUpdateForm"]//div[div[@id="translator-permissions-options"]]/p)')));
        $this->assertDatabaseCount(config('interpresso.table_translators'), 1);
    }

    #[Test]
    #[DataProvider('loaders')]
    public function password_errors_render_without_changing_the_saved_password(bool $database): void
    {
        $this->useLoader($database);
        $translator = $this->createUser(Language::all());
        $hash = $translator->password;
        $token = resolve(\AnyMedia\Interpresso\Services\TranslatorPasswords::class)->broker()->createToken($translator);
        $form = route('interpresso.password.reset', ['token' => $token, 'email' => $translator->email]);
        $this->from($form)->post(route('interpresso.password.update'), [
            'email' => $translator->email, 'token' => $token,
            'password' => 'replacement-password', 'password_confirmation' => 'different-password',
        ])->assertRedirect($form)->assertSessionHasErrors('password_confirmation');
        $document = $this->document($this->get($form)->assertOk()->getContent());
        $this->assertSame('The password confirmation must match the password.', trim($document->evaluate('string(//input[@id="password_confirmation"]/following-sibling::p[1])')));
        $this->assertSame('', $document->evaluate('string(//input[@name="password"]/@value)'));
        $this->assertSame($hash, $translator->fresh()->password);
    }

    public static function loaders(): array
    {
        return ['database' => [true], 'files' => [false]];
    }

    #[Test]
    public function validation_keeps_host_messages_and_reviewed_database_overrides(): void
    {
        $this->useLoader(true);
        File::put(app()->langPath('en/validation.php'), "<?php return ['required' => 'Host: :attribute is required.'];");
        $language = Language::firstOrFail();
        Translation::create([
            'language_id' => $language->id, 'language_code' => 'en', 'namespace' => '',
            'type' => 'php', 'group' => 'validation', 'key' => 'same', 'shared_identifier' => 'validation.same',
            'value' => 'Unreviewed draft', 'old_value' => ':attribute must equal :other.', 'approved' => false, 'needs_translation' => false,
        ]);
        $validator = app('validator')->make(['password' => 'correct', 'password_confirmation' => 'incorrect'], [
            'languages' => 'required', 'password_confirmation' => 'same:password',
        ]);
        $this->assertSame('Host: languages is required.', $validator->errors()->first('languages'));
        $this->assertSame('password confirmation must equal password.', $validator->errors()->first('password_confirmation'));
        $this->assertSame('The email field must be a valid email address.', __('validation.email', ['attribute' => 'email']));
    }

    private function document(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new DOMXPath($document);
    }
}
