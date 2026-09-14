<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use AnyMedia\Interpresso\Jobs\ApproveLanguagesJob;
use AnyMedia\Interpresso\Jobs\Batch\BatchProcessor;
use AnyMedia\Interpresso\Jobs\ExportTranslationJob;
use AnyMedia\Interpresso\Jobs\FindMissingTranslationsByLanguage;
use AnyMedia\Interpresso\Jobs\FindMissingTranslationsJob;
use AnyMedia\Interpresso\Jobs\ForceExportTranslationJob;
use AnyMedia\Interpresso\Jobs\ImportTranslationsJob;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\BatchService;
use AnyMedia\Interpresso\Services\ExportTranslationService;
use AnyMedia\Interpresso\Services\OpenAITranslationService;
use AnyMedia\Interpresso\Services\ProcessCapabilities;
use AnyMedia\Interpresso\Services\ProcessLock;
use AnyMedia\Interpresso\Tests\BaseTestCase;
use AnyMedia\Interpresso\Tests\Traits\InteractsWithBackgroundProcesses;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ChunkedOperationsTest extends BaseTestCase
{
    use RefreshDatabase, InteractsWithBackgroundProcesses;

    private Language $language;
    private Language $german;
    private int $adminId;

    public function setUp(): void
    {
        parent::setUp();
        $this->seedBrowserScenario();
        Translation::query()->delete();
        $this->language = Language::where('code', 'en')->firstOrFail();
        $this->german = Language::where('code', 'de')->firstOrFail();
        $this->adminId = Translator::where('admin', true)->firstOrFail()->id;
        $this->useDatabaseQueue();
        config(['interpresso.chunk_size' => 2]);
    }

    #[Test]
    #[DataProvider('exports')]
    public function exports_respect_the_slice_and_replays_preserve_existing_keys(string $type, bool $vendor, bool $force): void
    {
        $this->freezeSecond();
        $rows = $this->rows(5, ['type' => $type, 'is_vendor' => $vendor,
            'namespace' => $vendor ? 'cursor' : '', 'exported' => $force]);
        $path = app()->langPath(($vendor ? 'vendor/cursor/' : '') . ($type === 'json' ? 'en.json' : 'en/cursor.php'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $type === 'json' ? '{"keep":"Original"}' : "<?php return ['keep' => 'Original'];");
        $class = $force ? ForceExportTranslationJob::class : ExportTranslationJob::class;
        [$job, $batch] = (new $class($this->language))->withFakeBatch();
        config(['interpresso.chunk_size' => 50]); // The enqueued slice keeps its original size.
        $job->handle();
        $firstFile = File::get($path);
        $firstRows = Translation::orderBy('id')->get()->toArray();
        $job->handle();
        $this->assertSame($firstFile, File::get($path));
        $this->assertSame($firstRows, Translation::orderBy('id')->get()->toArray());
        $this->assertStringContainsString('Value 2', $firstFile);
        $this->assertStringNotContainsString('Value 3', $firstFile);
        $this->assertStringContainsString('Original', $firstFile);
        $this->assertCount(2, $batch->added);
        $this->assertSame($rows[1]->id, $batch->added[0]->afterId);
        $this->assertSame($rows[1]->id, $batch->added[1]->afterId);
    }

    public static function exports(): iterable
    {
        foreach (['php', 'json'] as $type) {
            foreach ([false, true] as $vendor) {
                foreach ([false, true] as $force) yield [$type, $vendor, $force];
            }
        }
    }

    #[Test]
    public function approval_replay_keeps_its_boundary_and_original_attribution(): void
    {
        $rows = $this->rows(5, ['approved' => false]);
        $rows[0]->update(['approved' => true, 'approved_by' => $this->adminId]);
        [$job, $batch] = (new ApproveLanguagesJob($this->language, $this->adminId))->withFakeBatch();
        $job->handle();
        $saved = Translation::orderBy('id')->get()->toArray();
        $job->handle();
        $this->assertSame($saved, Translation::orderBy('id')->get()->toArray());
        $this->assertSame(2, Translation::where('approved', true)->count());
        $this->assertSame($rows[1]->id, $batch->added[0]->afterId);
        $this->assertFalse($rows[2]->fresh()->approved);
        $batch->added[0]->handle();
        $this->assertSame(4, Translation::where('approved', true)->count());
    }

    #[Test]
    #[DataProvider('fileTypes')]
    public function file_import_replay_preserves_edits_and_the_chain_imports_every_entry(string $type): void
    {
        $path = app()->langPath($type === 'json' ? 'en.json' : 'en/cursor.php');
        $values = ['nested' => ['a' => 'A', 'b' => 'B'], 'third' => 'C', 'fourth' => 'D', 'fifth' => 'E'];
        File::put($path, $type === 'json' ? json_encode($values) : '<?php return ' . var_export($values, true) . ';');
        [$job, $batch] = (new ImportTranslationsJob())->withFakeBatch();
        $job->handle();
        $this->assertSame(2, Translation::count());
        Translation::firstOrFail()->update(['value' => 'Administrator edit', 'approved' => false]);
        $saved = Translation::orderBy('id')->get()->toArray();
        $job->handle();
        $this->assertSame($saved, Translation::orderBy('id')->get()->toArray());
        // Continue one of the identical successors, then its terminal slice.
        $batch->added[0]->handle();
        $this->assertSame(4, Translation::count());
        $batch->added[2]->handle();
        $this->assertSame(5, Translation::count());
        $this->assertEqualsCanonicalizing(['nested.a', 'nested.b', 'third', 'fourth', 'fifth'], Translation::pluck('key')->all());
        $this->assertSame('Administrator edit', Translation::firstOrFail()->value);
    }

    public static function fileTypes(): array
    {
        return [['php'], ['json']];
    }

    #[Test]
    public function changing_an_import_file_between_chunks_fails_without_skipping_or_overwriting_rows(): void
    {
        File::put(app()->langPath('en.json'), '{"a":"A","b":"B","c":"C"}');
        [$job, $batch] = (new ImportTranslationsJob())->withFakeBatch();
        $job->handle();
        File::put(app()->langPath('en.json'), '{"new":"New","a":"Changed","b":"B","c":"C"}');
        try {
            $batch->added[0]->handle();
            $this->fail('Changed input must not move an entry cursor.');
        } catch (\AnyMedia\Interpresso\Exceptions\ImportTranslationsException $exception) {
            $this->assertStringContainsString('changed between chunks', $exception->getMessage());
        }
        $this->assertSame(['a' => 'A', 'b' => 'B'], Translation::pluck('value', 'key')->all());
    }

    #[Test]
    public function model_import_cursors_cover_sparse_string_keys_and_each_translatable_column(): void
    {
        Schema::create('cursor_import_models', function ($table): void {
            $table->string('id')->primary();
            $table->text('title');
            $table->text('description');
        });
        try {
            foreach (['a-10', 'c-90', 'z-1000'] as $id) {
                DB::table('cursor_import_models')->insert(['id' => $id,
                    'title' => json_encode(['en' => 'Title ' . $id, 'de' => 'Titel']),
                    'description' => json_encode(['en' => 'Description ' . $id]),
                ]);
            }
            Setting::firstOrFail()->update(['import_only_from_root_language' => true]);
            Setting::getFreshCached();
            config(['interpresso.translatable_models' => [CursorImportModel::class]]);
            $batch = resolve(BatchProcessor::class)->dispatch([new ImportTranslationsJob()]);
            $this->runWorker(once: true);
            $this->assertSame(2, Translation::count());
            $this->assertSame(['a-10', 'c-90'], Translation::orderBy('id')->pluck('key')->all());
            Translation::firstOrFail()->update(['value' => 'Saved draft']);
            [$replay] = (new ImportTranslationsJob())->withFakeBatch();
            $replay->handle();
            $this->assertSame(2, Translation::count());
            $this->assertSame('Saved draft', Translation::firstOrFail()->value);
            $this->runWorker();
            $this->assertTrue($batch->fresh()->finished());
            $this->assertSame(6, Translation::count());
            $this->assertSame(3, Translation::where('group', 'title')->count());
            $this->assertSame(3, Translation::where('group', 'description')->count());

            Translation::query()->update(['value' => 'Exported', 'exported' => false]);
            $export = resolve(BatchProcessor::class)->dispatch([new ExportTranslationJob($this->language, true)]);
            $this->runWorker();
            $this->assertTrue($export->fresh()->finished());
            foreach (DB::table('cursor_import_models')->get() as $row) {
                $this->assertSame(['en' => 'Exported', 'de' => 'Titel'], json_decode($row->title, true));
                $this->assertSame(['en' => 'Exported'], json_decode($row->description, true));
            }
        } finally {
            Schema::dropIfExists('cursor_import_models');
        }
    }

    #[Test]
    public function missing_translation_replay_skips_existing_rows_and_does_not_repeat_ai_calls(): void
    {
        $rows = $this->rows(5);
        Setting::firstOrFail()->update(['enable_open_ai_translations' => true]);
        Setting::getFreshCached();
        $this->mock(OpenAITranslationService::class)->shouldReceive('translateArray')->once()
            ->andReturn(['t_0' => 'Translated A', 't_1' => 'Translated B']);
        [$job, $batch] = (new FindMissingTranslationsByLanguage([$this->german->id], $this->language->id))->withFakeBatch();
        $job->handle();
        $this->assertSame(2, $this->german->translations()->count());
        $this->german->translations()->firstOrFail()->update(['value' => 'Saved edit']);
        $saved = $this->german->translations()->orderBy('id')->get()->toArray();
        $job->handle();
        $this->assertSame($saved, $this->german->translations()->orderBy('id')->get()->toArray());
        $this->assertSame($rows[1]->id, $batch->added[0]->afterId);
    }

    #[Test]
    public function finding_missing_translations_chains_to_completion_for_all_languages(): void
    {
        $this->rows(5);
        $french = Language::create(['code' => 'fr', 'name' => 'French', 'native_name' => 'Francais']);
        $batch = resolve(BatchProcessor::class)->dispatch([new FindMissingTranslationsJob()]);
        $this->assertSame(7, $batch->options['estimated_total_jobs']);
        $this->runWorker();
        $this->assertTrue($batch->fresh()->finished());
        foreach ([$this->german, $french] as $target) {
            $this->assertSame($this->language->translations()->pluck('value', 'key')->all(), $target->translations()->pluck('value', 'key')->all());
            $this->assertSame(5, $target->translations()->where('needs_translation', true)->where('approved', false)->count());
        }
        $this->assertFalse(resolve(ProcessLock::class)->isLocked());
    }

    #[Test]
    #[DataProvider('operations')]
    public function cancelled_batches_never_process_or_chain(string $operation): void
    {
        $this->rows(5, ['approved' => false]);
        File::put(app()->langPath('en.json'), '{"import":"Value"}');
        $job = match ($operation) {
            'approve' => new ApproveLanguagesJob($this->language, $this->adminId),
            'export' => new ExportTranslationJob($this->language),
            'force' => new ForceExportTranslationJob($this->language),
            'missing' => new FindMissingTranslationsJob(),
            'missing-language' => new FindMissingTranslationsByLanguage([$this->german->id], $this->language->id),
            'import' => new ImportTranslationsJob(),
        };
        [$job, $batch] = $job->withFakeBatch();
        $batch->cancel();
        $saved = Translation::orderBy('id')->get()->toArray();
        $job->handle();
        $this->assertSame($saved, Translation::orderBy('id')->get()->toArray());
        $this->assertSame([], $batch->added);
        $this->assertSame('{"import":"Value"}', File::get(app()->langPath('en.json')));
    }

    public static function operations(): array
    {
        return array_map(fn ($operation) => [$operation], ['approve', 'export', 'force', 'missing', 'missing-language', 'import']);
    }

    #[Test]
    public function cancellation_during_a_slice_prevents_its_successor(): void
    {
        $this->rows(5);
        [$job, $batch] = (new ExportTranslationJob($this->language))->withFakeBatch();
        $this->mock(ExportTranslationService::class)->shouldReceive('exportChunk')->once()
            ->andReturnUsing(function (...$arguments) use ($batch): ?int {
                $cursor = (new ExportTranslationService())->exportChunk(...$arguments);
                $batch->cancel();
                return $cursor;
            });
        $job->handle();
        $this->assertSame(2, Translation::where('exported', true)->count());
        $this->assertSame([], $batch->added);
    }

    #[Test]
    public function each_tick_renews_the_lease_past_its_original_expiry_and_the_final_chunk_releases_it(): void
    {
        $this->freezeSecond();
        config(['interpresso.process_lock_ttl' => 20, 'interpresso.queue_worker.max_jobs' => 1]);
        $this->rows(5);
        $batch = resolve(BatchProcessor::class)->dispatch([new ExportTranslationJob($this->language)]);
        $originalExpiry = Setting::firstOrFail()->process_expires_at;
        $owner = Setting::firstOrFail()->process_owner;
        foreach ([33, 67] as $progress) {
            $this->travel(15)->seconds();
            $this->artisan('interpresso:work')->assertExitCode(0);
            $setting = Setting::firstOrFail();
            $this->assertTrue($setting->process_expires_at->greaterThan($originalExpiry));
            $this->assertSame($owner, $setting->process_owner);
            $this->assertFalse((new ProcessLock())->acquire('competing operation', 20));
            $this->assertSame($progress, resolve(BatchService::class)->progress($batch->id)['progress']);
        }
        $this->travel(15)->seconds();
        $this->artisan('interpresso:work')->assertExitCode(0);
        $this->assertTrue($batch->fresh()->finished());
        $this->assertFalse((new ProcessLock())->isLocked());
        $this->assertNull(Setting::firstOrFail()->process_owner);
        $this->assertSame(5, Translation::where('exported', true)->count());
        $this->assertSame(100, resolve(BatchService::class)->progress($batch->id)['progress']);
    }

    #[Test]
    public function the_foreground_scheduler_callback_drains_a_real_batch_without_spawning_a_process(): void
    {
        $this->rows(5, ['approved' => false]);
        config(['interpresso.schedule.queue_worker' => true]);
        $this->mock(ProcessCapabilities::class)->shouldReceive('canSpawn')->once()->andReturn(false);
        $batch = resolve(BatchProcessor::class)->dispatch([new ApproveLanguagesJob($this->language, $this->adminId)]);
        $worker = $this->app->make(Schedule::class)->events()[0];
        $this->assertInstanceOf(CallbackEvent::class, $worker);
        $this->assertFalse($worker->runInBackground);
        $worker->run($this->app);
        $this->assertTrue($batch->fresh()->finished());
        $this->assertSame(0, Translation::where('approved', false)->count());
        $this->assertFalse((new ProcessLock())->isLocked());
        $this->assertFalse($worker->mutex->exists($worker));
    }

    private function rows(int $count, array $attributes = []): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = Translation::create(array_replace([
                'language_id' => $this->language->id, 'language_code' => 'en', 'shared_identifier' => 'cursor-' . $i,
                'type' => 'php', 'namespace' => '', 'group' => 'cursor', 'is_vendor' => false,
                'key' => 'nested.key' . $i, 'value' => 'Value ' . $i, 'approved' => true,
                'updated_translation' => false, 'needs_translation' => false, 'exported' => false,
            ], $attributes));
        }
        return $rows;
    }
}

class CursorImportModel extends Model
{
    protected $table = 'cursor_import_models';
    public array $translatable = ['title', 'description'];
}
