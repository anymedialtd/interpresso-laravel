<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\OpenAITranslationService;
use AnyMedia\Interpresso\Tests\BaseTestCase;
use AnyMedia\Interpresso\Tests\Traits\InteractsWithBackgroundProcesses;

class HttpControlCoverageTest extends BaseTestCase
{
    use RefreshDatabase, InteractsWithBackgroundProcesses;

    #[Test]
    public function import_buttons_execute_real_batches_and_persist_file_contents(): void
    {
        $this->seedBrowserScenario('imports');
        $this->post(route('interpresso.languages.import-languages'))->assertRedirect()->assertSessionHas('batch_id');
        $this->assertDatabaseHas(config('interpresso.table_languages'), ['code' => 'it']);
        $this->assertSuccessfulBatch([__('interpresso::languages.import_languages_success', ['languages' => 'Italian']) . __('interpresso::global.reload_suggestion')]);
        $this->post(route('interpresso.languages.import-translations'))->assertRedirect()->assertSessionHas('batch_id');
        $this->assertDatabaseHas(config('interpresso.table_translations'), ['key' => 'imported', 'value' => 'Imported from a real PHP file']);
        $this->assertDatabaseHas(config('interpresso.table_translations'), ['key' => 'Imported JSON key', 'value' => 'Imported from a real JSON file']);
        $this->assertSuccessfulBatch(array_map(fn ($code) => __('interpresso::languages.import_translations_success', ['total' => $code === 'en' ? 2 : 0, 'language_code' => $code]) . __('interpresso::global.reload_suggestion'), ['en', 'de', 'it']));
        $this->assertSame(0, DB::table('job_batches')->sum('failed_jobs'));
        $this->assertSame(0, DB::table('job_batches')->whereNull('finished_at')->count());
    }

    #[Test]
    public function find_missing_and_approve_buttons_execute_real_jobs_for_both_languages(): void
    {
        $this->seedBrowserScenario();
        $german = Language::where('code', 'de')->firstOrFail();
        $this->post(route('interpresso.languages.find-missing'))->assertRedirect()->assertSessionHas('batch_id');
        $this->assertSame(6, $german->translations()->count());
        $this->assertSame(6, $german->translations()->where('needs_translation', true)->count());
        $this->assertSuccessfulBatch(array_map(fn ($code) => __('interpresso::languages.find_missing_translations_success', ['total' => $code === 'de' ? 6 : 0, 'language_code' => $code]) . __('interpresso::global.reload_suggestion'), ['en', 'de']));
        $this->post(route('interpresso.languages.approve'))->assertRedirect()->assertSessionHas('batch_id');
        $this->assertSame(0, Translation::where('approved', false)->count());
        $this->assertSame(0, Translation::where('needs_translation', true)->count());
        $this->assertSuccessfulBatch(array_map(fn ($language) => __('interpresso::translations.approved_language_success', ['language' => $language, 'total' => $language === 'English' ? 3 : 6]) . __('interpresso::global.reload_suggestion'), ['English', 'German']));
        $this->assertSame(0, DB::table('job_batches')->sum('failed_jobs'));
        $this->post(route('interpresso.languages.approve'))->assertRedirect()->assertSessionHas('toast.message', 'Nothing approved.');
    }

    #[Test]
    #[DataProvider('exportScopes')]
    public function export_buttons_write_files_and_leave_ineligible_rows_unchanged(bool $all): void
    {
        $this->seedBrowserScenario('bulk');
        $english = Language::where('code', 'en')->firstOrFail();
        $german = Language::where('code', 'de')->firstOrFail();
        $german->translations()->firstOrFail()->update(['approved' => true]);
        $url = $all ? route('interpresso.languages.export') : route('interpresso.translations.export', $english);
        $before = Translation::where('key', 'checkout')->firstOrFail()->getAttributes();
        $this->post($url, ['exportOnlyModels' => false])->assertRedirect()->assertSessionHas('batch_id');
        $this->assertSuccessfulBatch([($all
            ? __('interpresso::translations.export_languages_success', ['languages' => 'English, German', 'total' => 2])
            : __('interpresso::translations.export_language_success', ['language' => 'English', 'total' => 1])) . __('interpresso::global.reload_suggestion')]);
        $exported = require app()->langPath('vendor/e2e-vendor/en/e2e.php');
        $this->assertSame('Vendor notice', $exported['vendor_notice']);
        $this->assertArrayNotHasKey('vendor_pending', $exported);
        $this->assertTrue(Translation::where('key', 'vendor_notice')->firstOrFail()->exported);
        $this->assertSame($before, Translation::where('key', 'checkout')->firstOrFail()->getAttributes());
        $this->assertSame($all, $german->translations()->firstOrFail()->exported);
        if ($all) {
            $this->assertSame('Willkommen zu Hause', (require app()->langPath('de/e2e.php'))['welcome']);
        }
        $this->assertSame(0, DB::table('job_batches')->sum('failed_jobs'));
    }

    public static function exportScopes(): array
    {
        return ['one language' => [false], 'all languages' => [true]];
    }

    #[Test]
    #[DataProvider('exportScopes')]
    public function model_only_exports_update_the_json_column_and_leave_file_rows_untouched(bool $all): void
    {
        $this->seedBrowserScenario('models');
        $english = Language::where('code', 'en')->firstOrFail();
        $url = $all ? route('interpresso.languages.export') : route('interpresso.translations.export', $english);
        $this->post($url, ['exportOnlyModels' => true])->assertRedirect()->assertSessionHas('batch_id');
        $this->assertSuccessfulBatch([($all
            ? __('interpresso::translations.export_languages_success', ['languages' => 'English, German', 'total' => 2])
            : __('interpresso::translations.export_language_success', ['language' => 'English', 'total' => 1])) . __('interpresso::global.reload_suggestion')]);
        $this->assertSame(['en' => 'Model English', 'de' => $all ? 'Model German' : 'Old German'], json_decode(DB::table('e2e_articles')->value('title'), true));
        $this->assertFalse(Translation::where('key', 'vendor_notice')->firstOrFail()->exported);
        $this->assertSame(0, DB::table('job_batches')->sum('failed_jobs'));
    }

    #[Test]
    public function language_approval_leaves_other_languages_unchanged(): void
    {
        $this->seedBrowserScenario('bulk');
        $english = Language::where('code', 'en')->firstOrFail();
        $german = Translation::where('language_code', 'de')->firstOrFail();
        $before = $german->getAttributes();
        $this->post(route('interpresso.translations.approve-all', $english))->assertRedirect()->assertSessionHas('batch_id');
        $this->assertSame(0, $english->translations()->where('approved', false)->count());
        $this->assertSame($before, $german->fresh()->getAttributes());
        $this->assertSuccessfulBatch([__('interpresso::translations.approved_language_success', ['language' => 'English', 'total' => 3]) . __('interpresso::global.reload_suggestion')]);
    }

    #[Test]
    public function cancel_jobs_preserves_unrelated_work_and_unblocks_a_real_mutation(): void
    {
        $this->seedBrowserScenario('running');
        $foreign = Bus::batch([])->name('another-package')->dispatch();
        DB::table('job_batches')->where('id', $foreign->id)->update(['finished_at' => null]);
        $job = DB::table('jobs')->insertGetId(['queue' => 'another-queue', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
        $this->post(route('interpresso.languages.cancel-jobs'))->assertRedirect()->assertSessionHas('toast.message', '1 Batches deleted. 1 Jobs deleted.');
        $this->assertFalse(Setting::firstOrFail()->process_running);
        $this->assertSame(0, DB::table('jobs')->where('queue', config('interpresso.queue_name'))->count());
        $this->assertDatabaseHas('jobs', ['id' => $job, 'queue' => 'another-queue']);
        $this->assertDatabaseHas('job_batches', ['id' => $foreign->id, 'cancelled_at' => null]);
        $this->getJson(route('interpresso.batch.progress'))->assertOk()->assertJsonPath('finished', true);
        $this->getJson(route('interpresso.batch.progress', ['id' => $foreign->id]))->assertForbidden();
        $this->post(route('interpresso.languages.approve'))->assertRedirect();
        $this->assertSame(0, Translation::where('approved', false)->count());
    }

    #[Test]
    public function auto_translate_saves_root_and_other_languages_using_the_actual_job(): void
    {
        $this->seedBrowserScenario('examples');
        Setting::firstOrFail()->update(['enable_open_ai_translations' => true]);
        Setting::getFreshCached();
        $root = Translation::where('key', 'welcome')->where('language_code', 'en')->firstOrFail();
        $this->mock(OpenAITranslationService::class)->shouldReceive('translateString')->once()
            ->withArgs(fn ($from, $to, $value) => $from->code === 'en' && $to->code === 'de' && $value === 'Changed root')->andReturn('Geaenderter Text');
        $this->post(route('interpresso.translations.update-all', ['language' => $root->language, 'id' => $root->id]), ['translatedValue' => 'Changed root'])
            ->assertRedirect()->assertSessionHas('batch_id');
        $this->assertSame('Changed root', $root->fresh()->value);
        $other = Translation::where('language_code', 'de')->firstOrFail();
        $this->assertSame('Geaenderter Text', $other->value);
        $this->assertSame('Willkommen zu Hause', $other->old_value);
        $this->assertFalse($other->approved);
        $this->assertTrue($other->updated_translation);
        $this->assertSuccessfulBatch([__('interpresso::translations.updated_all_languages', ['translation_id' => $root->id]) . ' ' . __('interpresso::global.reload_suggestion')]);
    }

    #[Test]
    public function suggestion_denials_and_service_failure_never_modify_the_translation(): void
    {
        $this->seedBrowserScenario();
        $root = Translation::where('key', 'welcome')->firstOrFail();
        $url = route('interpresso.translations.suggest', ['language' => $root->language, 'id' => $root->id]);
        $before = $root->getAttributes();
        $this->postJson($url)->assertForbidden();
        $this->assertSame($before, $root->fresh()->getAttributes());
        Setting::firstOrFail()->update(['enable_open_ai_translations' => true]);
        Setting::getFreshCached();
        $this->mock(OpenAITranslationService::class)->shouldReceive('translateString')->once()->andThrow(new \RuntimeException('Service failed'));
        $this->postJson($url)->assertStatus(502)->assertJsonPath('message', __('interpresso::global.something_wrong'));
        $this->assertSame($before, $root->fresh()->getAttributes());
        $root->update(['value' => '']);
        $before = $root->fresh()->getAttributes();
        $this->postJson($url)->assertStatus(422);
        $this->assertSame($before, $root->fresh()->getAttributes());
    }

    #[Test]
    #[DataProvider('settingsFields')]
    public function every_setting_saves_only_its_own_value_and_rejects_invalid_values(string $field): void
    {
        $this->actingAs(Translator::firstOrFail());
        Setting::firstOrFail()->update(['domains' => 'https://saved.example']);
        foreach ([true, false] as $value) {
            $before = Setting::firstOrFail()->getAttributes();
            $this->post(route('interpresso.settings.update', $field), [$field => $value, 'process_running' => true])
                ->assertRedirect(route('interpresso.settings'))->assertSessionHasNoErrors();
            $after = Setting::firstOrFail();
            $this->assertSame($value, $after->$field);
            unset($before['updated_at'], $before[$field]);
            $attributes = $after->getAttributes();
            unset($attributes['updated_at'], $attributes[$field]);
            $this->assertSame($before, $attributes);
        }
        $before = Setting::firstOrFail()->getAttributes();
        $this->post(route('interpresso.settings.update', $field), [$field => 'invalid'])->assertSessionHasErrors($field);
        $this->assertSame($before, Setting::firstOrFail()->getAttributes());
    }

    public static function settingsFields(): array
    {
        return array_map(fn ($field) => [$field], ['enable_multi_host', 'db_loader', 'import_vendor', 'enable_pending_notifications', 'enable_automatic_pending_notifications', 'enable_open_ai_translations', 'import_only_from_root_language', 'allow_deleting_languages']);
    }

    #[Test]
    public function pending_notifications_are_delivered_and_can_be_read_by_the_assigned_translator(): void
    {
        $this->seedBrowserScenario();
        config(['mail.default' => 'array']);
        Setting::firstOrFail()->update(['enable_pending_notifications' => true]);
        Setting::getFreshCached();
        $recipient = Translator::where('email', 'translator@example.test')->firstOrFail();
        $this->post(route('interpresso.translators.notify', $recipient))->assertRedirect();
        $this->actingAs($recipient);
        $response = $this->getJson(route('interpresso.notifications'))->assertOk()->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.message', 'You have pending translations. Total: 2. Please login and check the translations that needs to be translated.');
        $this->postJson($response->json('notifications.0.read_url'), ['read' => true])->assertOk()->assertJsonCount(0, 'notifications');
        $this->getJson(route('interpresso.notifications'))->assertOk()->assertJsonCount(0, 'notifications');
    }

    #[Test]
    public function disabled_pending_notifications_reject_admin_requests_without_sending_or_writing(): void
    {
        $this->seedBrowserScenario();
        Notification::fake();
        Setting::firstOrFail()->update(['enable_pending_notifications' => false]);
        Setting::getFreshCached();
        $recipient = Translator::where('email', 'translator@example.test')->firstOrFail();
        $before = $recipient->getAttributes();
        $this->post(route('interpresso.translators.notify', $recipient))->assertForbidden();
        Notification::assertNothingSent();
        $this->assertSame($before, $recipient->fresh()->getAttributes());
        $this->assertDatabaseCount('notifications', 0);
    }
}
