<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Tests\BaseTestCase;

class LanguageTest extends BaseTestCase
{
    use RefreshDatabase;

    private Translator $admin;
    private Translator $translator;
    private Language $language;

    public function setUp(): void
    {
        parent::setUp();
        $this->language = Language::query()->firstOrFail();
        $this->admin = Translator::query()->firstOrFail();
        $this->translator = $this->createUser(Language::all());
        Setting::query()->firstOrFail()->update(['allow_deleting_languages' => true]);
        Setting::getFreshCached();
    }

    #[Test]
    public function lang_create_language(): void
    {
        $language = factory(Language::class)->create();
        $this->assertDatabaseHas(config('interpresso.table_languages'), [
            'name' => $language->name, 'native_name' => $language->native_name, 'code' => $language->code,
        ]);
    }

    #[Test]
    public function admin_sees_admin_options(): void
    {
        $this->actingAs($this->admin)->get(route('interpresso.languages'))->assertOk()
            ->assertSee($this->language->native_name)
            ->assertSee(__('interpresso::languages.button.import_languages'))
            ->assertSee(__('interpresso::languages.button.import_translations'))
            ->assertSee(__('interpresso::languages.button.add_language'))
            ->assertSee(__('interpresso::languages.button.find_missing_translations'))
            ->assertSee(__('interpresso::table.delete'));
    }

    #[Test]
    public function translator_sees_only_assigned_languages_without_admin_actions(): void
    {
        Language::query()->create(['name' => 'German', 'native_name' => 'Deutsch', 'code' => 'de']);
        $this->actingAs($this->translator)->get(route('interpresso.languages'))->assertOk()
            ->assertSee($this->language->native_name)->assertDontSee('Deutsch')
            ->assertDontSee(__('interpresso::languages.button.import_languages'))
            ->assertDontSee(__('interpresso::languages.button.import_translations'))
            ->assertDontSee(__('interpresso::languages.button.add_language'))
            ->assertDontSee(__('interpresso::languages.button.find_missing_translations'))
            ->assertDontSee(__('interpresso::table.delete'));
    }

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get(route('interpresso.languages'))->assertRedirect(route('interpresso.login'));
    }

    #[Test]
    public function admin_can_add_language(): void
    {
        $this->actingAs($this->admin)->post(route('interpresso.languages.store'), ['language' => 'de'])
            ->assertSessionHasNoErrors()->assertRedirect(route('interpresso.languages'));
        $this->get(route('interpresso.languages'))->assertOk()->assertSee('Deutsch')->assertSee($this->language->native_name);
        $this->assertSame(2, Language::count());
    }

    #[Test]
    public function admin_can_delete_language(): void
    {
        $language = Language::query()->create(['name' => 'German', 'native_name' => 'Deutsch', 'code' => 'de']);
        $this->actingAs($this->admin)->post(route('interpresso.languages.delete', $language))->assertRedirect();
        $this->get(route('interpresso.languages'))->assertOk()->assertDontSee('Deutsch')->assertSee($this->language->native_name);
        $this->assertDatabaseMissing(config('interpresso.table_languages'), ['id' => $language->id]);
    }

    #[Test]
    public function admin_can_search_languages(): void
    {
        foreach ([['de', 'German', 'Deutsch'], ['fr', 'French', 'français']] as [$code, $name, $native]) {
            Language::query()->create(['code' => $code, 'name' => $name, 'native_name' => $native]);
        }
        $this->actingAs($this->admin)->get(route('interpresso.languages'))->assertSee('Deutsch')->assertSee('français');
        $this->get(route('interpresso.languages', ['search' => 'English']))->assertOk()
            ->assertSee($this->language->native_name)->assertDontSee('Deutsch')->assertDontSee('français');
    }
}
