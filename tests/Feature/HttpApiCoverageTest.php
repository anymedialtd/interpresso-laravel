<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Tests\BaseTestCase;

class HttpApiCoverageTest extends BaseTestCase
{
    use RefreshDatabase;

    private const KEY = 'http-tests-only-shared-key';

    public function setUp(): void
    {
        parent::setUp();
        config(['interpresso.api_shared_api_key' => self::KEY]);
    }

    #[Test]
    public function language_list_and_version_return_their_real_payloads(): void
    {
        $language = Language::firstOrFail();
        $this->postJson(route('interpresso.api.get-languages'), ['api_key' => self::KEY])->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $language->id)->assertJsonPath('data.0.code', $language->code);
        $this->getJson(route('interpresso.api.version'))->assertOk()->assertJsonPath('version', \AnyMedia\Interpresso\InterpressoServiceProvider::$version);
    }

    #[Test]
    public function paginated_translations_return_both_pages_without_losing_rows(): void
    {
        $language = Language::firstOrFail();
        $records = [];
        for ($i = 0; $i < 501; $i++) {
            $records[] = ['language_id' => $language->id, 'language_code' => $language->code, 'type' => 'json',
                'shared_identifier' => 'api-' . $i, 'key' => 'api-' . $i, 'value' => 'Value ' . $i, 'needs_translation' => false];
        }
        Translation::insert($records);
        $first = $this->postJson(route('interpresso.api.get-paginated-translations'), ['api_key' => self::KEY])
            ->assertOk()->assertJsonCount(500, 'data')->assertJsonPath('meta.total', 501);
        $second = $this->postJson($first->json('links.next'), ['api_key' => self::KEY])->assertOk()->assertJsonCount(1, 'data');
        $ids = [...array_column($first->json('data'), 'id'), ...array_column($second->json('data'), 'id')];
        $this->assertCount(501, array_unique($ids));
        $this->assertEqualsCanonicalizing(Translation::pluck('id')->all(), $ids);
    }

    #[Test]
    public function api_cancellation_stops_only_the_package_queue_and_progress_reflects_it(): void
    {
        $batch = Bus::batch([])->name(config('interpresso.batch_name'))->dispatch();
        DB::table('job_batches')->where('id', $batch->id)->update(['finished_at' => null]);
        foreach ([config('interpresso.queue_name'), 'unrelated'] as $queue) {
            DB::table('jobs')->insert(['queue' => $queue, 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
        }
        $this->postJson(route('interpresso.api.jobs-running'), ['api_key' => self::KEY])->assertOk()->assertJsonPath('process_running', true);
        $this->postJson(route('interpresso.api.cancel-batch'), ['api_key' => self::KEY])->assertNoContent();
        $this->assertNotNull(DB::table('job_batches')->where('id', $batch->id)->value('cancelled_at'));
        $this->assertSame(['unrelated'], DB::table('jobs')->pluck('queue')->all());
        $this->postJson(route('interpresso.api.jobs-running'), ['api_key' => self::KEY])->assertOk()->assertJsonPath('process_running', false);
    }

    #[Test]
    public function force_export_executes_after_the_response_and_reexports_previously_exported_values(): void
    {
        $language = Language::firstOrFail();
        File::put(app()->langPath('en.json'), '{"force":"stale file"}');
        $language->translations()->create(['language_code' => 'en', 'type' => 'json', 'namespace' => '', 'group' => '',
            'shared_identifier' => 'force-export', 'key' => 'force', 'value' => 'Current database value', 'approved' => true, 'exported' => true, 'needs_translation' => false]);
        $this->postJson(route('interpresso.api.force-export'), ['api_key' => self::KEY])->assertOk()
            ->assertJsonPath('message', __('interpresso::translations.export_on_other_host_started', ['host' => 'http://localhost']));
        $this->assertSame('Current database value', json_decode(File::get(app()->langPath('en.json')), true)['force']);
        $this->assertSame(0, DB::table('job_batches')->sum('failed_jobs'));
        $this->assertFalse(Setting::firstOrFail()->process_running);
    }

    #[Test]
    #[DataProvider('protectedApiRoutes')]
    public function every_api_endpoint_rejects_missing_or_wrong_keys_even_for_a_logged_in_admin(string $name): void
    {
        $admin = Translator::firstOrFail();
        $this->actingAs($admin);
        $tables = [config('interpresso.table_languages'), config('interpresso.table_translators'), config('interpresso.table_translations'),
            config('interpresso.table_translator_language'), config('interpresso.table_settings'), 'notifications', 'jobs', 'job_batches'];
        $snapshot = fn () => array_map(fn ($table) => DB::table($table)->get()->toJson(), $tables);
        $before = $snapshot();
        foreach ([[[], 422], [['api_key' => 'wrong-key'], 401], [['api_key' => []], 422]] as [$payload, $status]) {
            $this->postJson(route($name), $payload)->assertStatus($status);
            $this->assertSame($before, $snapshot(), $name);
        }
    }

    public static function protectedApiRoutes(): array
    {
        return array_map(fn ($route) => ['interpresso.api.' . $route], ['cancel-batch', 'jobs-running', 'get-languages', 'get-paginated-translations', 'force-export']);
    }
}
