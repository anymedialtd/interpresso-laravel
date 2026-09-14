<?php

namespace AnyMedia\Interpresso\Tests\MySql;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use AnyMedia\Interpresso\InterpressoServiceProvider;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Services\ProcessLock;

// Separate opt-in suite. The ordinary PHPUnit suite remains SQLite in memory.
class ReleaseDatabaseTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [InterpressoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        if (getenv('DB_DATABASE') !== 'interpresso_ci') {
            throw new \RuntimeException('MySQL integration tests require the disposable interpresso_ci database.');
        }
        $app['config']->set([
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'driver' => 'mysql', 'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '3306', 'database' => 'interpresso_ci',
                'username' => getenv('DB_USERNAME') ?: 'interpresso', 'password' => getenv('DB_PASSWORD') ?: 'interpresso',
                'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
            ],
            'interpresso.db_connection' => 'mysql', 'cache.default' => 'array', 'queue.default' => 'sync',
            'queue.batching.database' => 'mysql', 'queue.failed.database' => 'mysql', 'mail.default' => 'array',
        ]);
        $app->useLangPath(dirname(__DIR__, 2) . '/build/mysql-lang');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        File::ensureDirectoryExists(app()->langPath('en'));
        Setting::getFreshCached();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->app) File::deleteDirectory($this->app->langPath());
        } finally {
            parent::tearDown();
        }
    }

    #[Test]
    public function migrations_rollback_and_reapply_with_the_foreign_key_supported_and_identifiers_under_60_characters(): void
    {
        $table = config('interpresso.table_translations');
        $this->assertTrue(Schema::hasIndex($table, 'ltr_lang_approved_idx'));
        // Reproduce MySQL dropping the original redundant foreign-key index.
        if (Schema::hasIndex($table, 'interpresso_translations_language_id_foreign')) {
            Schema::table($table, fn ($blueprint) => $blueprint->dropIndex('interpresso_translations_language_id_foreign'));
        }
        $this->artisan('migrate:rollback', ['--step' => 6, '--force' => true])->assertExitCode(0);
        $this->assertFalse(Schema::hasIndex($table, 'ltr_lang_approved_idx'));
        $this->assertTrue(Schema::hasIndex($table, 'interpresso_translations_language_id_foreign'));
        $this->assertNotEmpty(Schema::getForeignKeys($table));
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertTrue(Schema::hasIndex($table, 'ltr_lang_approved_idx'));
        $this->artisan('migrate:reset', ['--force' => true])->assertExitCode(0);
        $this->assertFalse(Schema::hasTable($table));
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $indexes = DB::select('SELECT INDEX_NAME AS name FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
            UNION SELECT CONSTRAINT_NAME AS name FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()');
        $this->assertNotEmpty($indexes);
        foreach ($indexes as $index) $this->assertLessThanOrEqual(60, strlen($index->name), $index->name);
        $this->assertTrue(Schema::hasIndex($table, 'ltr_lang_approved_idx'));
    }

    #[Test]
    public function translator_reset_tokens_support_custom_tables_and_repeatable_migration_on_mysql(): void
    {
        $original = config('interpresso.table_password_reset_tokens');
        $name = 'custom_translator_password_reset_tokens_with_a_long_table_name';
        config(['interpresso.table_password_reset_tokens' => $name]);
        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_09_14_000002_create_translator_password_reset_tokens_table.php';
        try {
            $migration->up();
            $migration->up();
            DB::table($name)->insert(['email' => 'recipient@example.test', 'token' => 'hashed-token', 'created_at' => now()]);
            $migration->up();
            $this->assertSame('hashed-token', DB::table($name)->value('token'));
            $indexes = DB::select('SELECT INDEX_NAME AS name FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$name]);
            $this->assertNotEmpty($indexes);
            foreach ($indexes as $index) $this->assertLessThanOrEqual(60, strlen($index->name), $index->name);
            $migration->down();
            $migration->down();
            $this->assertFalse(Schema::hasTable($name));
            $migration->up();
            $this->assertTrue(Schema::hasColumns($name, ['email', 'token', 'created_at']));
        } finally {
            $migration->down();
            config(['interpresso.table_password_reset_tokens' => $original]);
        }
    }

    #[Test]
    public function translator_locale_migration_can_rollback_and_reapply_without_backfilling_existing_rows(): void
    {
        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_09_14_000001_add_locale_to_translators_table.php';
        $table = config('interpresso.table_translators');
        $this->assertNull(DB::table($table)->value('locale'));
        DB::table($table)->update(['locale' => 'de']);
        $migration->up();
        $this->assertSame('de', DB::table($table)->value('locale'));
        $migration->down();
        $migration->down();
        $this->assertFalse(Schema::hasColumn($table, 'locale'));
        $migration->up();
        $migration->up();
        $this->assertNull(DB::table($table)->value('locale'));
        $this->assertSame('admin@admin.com', DB::table($table)->value('email'));
    }

    private function remoteData(): array
    {
        $language = Language::firstOrFail();
        $row = Translation::create([
            'language_id' => $language->id, 'language_code' => 'en', 'shared_identifier' => 'download-first',
            'type' => 'php', 'namespace' => '', 'group' => 'download', 'key' => 'nested.first',
            'value' => 'Remote first', 'approved' => true, 'needs_translation' => false,
            'updated_translation' => false, 'exported' => true,
        ]);
        $first = $row->getAttributes();
        $second = array_replace($first, ['id' => $row->id + 1, 'key' => 'nested.second', 'shared_identifier' => 'download-second', 'value' => 'Remote second']);
        $row->update(['value' => 'Local work to replace']);
        return [$language->getAttributes(), $first, $second];
    }

    #[Test]
    public function process_lock_migration_and_lease_lifecycle_work_on_mysql(): void
    {
        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_09_14_000000_add_process_lock_to_settings_table.php';
        $table = config('interpresso.table_settings');
        $migration->down();
        $migration->down();
        $this->assertFalse(Schema::hasColumn($table, 'process_owner'));
        $migration->up();
        $migration->up();
        $this->assertTrue(Schema::hasColumns($table, ['process_owner', 'process_started_at', 'process_expires_at']));
        $first = new ProcessLock();
        $second = new ProcessLock();
        $this->assertTrue($first->acquire('mysql:123 import', 900));
        $this->assertFalse($second->acquire('mysql:456 export', 900));
        $first->refresh(); // Includes an unchanged heartbeat in the same second.
        $first->release();
        $this->assertTrue($second->releaseExpired()); // Already clear on MySQL too.
        $this->assertTrue($second->acquire('mysql:456 export', 900));
        $first->release();
        $this->assertTrue($second->isLocked());
        $second->release();
        $this->assertNull($second->current());
    }

    #[Test]
    #[DataProvider('loaderModes')]
    public function developer_download_replaces_all_pages_and_force_exports_only_in_file_mode(bool $dbLoader): void
    {
        [$language, $first, $second] = $this->remoteData();
        Setting::firstOrFail()->update(['db_loader' => $dbLoader, 'enable_multi_host' => false]);
        Setting::getFreshCached();
        config(['interpresso.api_shared_api_key' => 'integration-secret', 'interpresso.main_server_domain' => 'https://source.example']);
        $languageUrl = 'https://source.example' . route('interpresso.api.get-languages', [], false);
        $translationUrl = 'https://source.example' . route('interpresso.api.get-paginated-translations', [], false);
        Http::fake([
            $languageUrl => Http::response(['data' => [$language]]),
            $translationUrl => Http::response(['data' => [$first], 'meta' => ['last_page' => 2]]),
            $translationUrl . '?page=2' => Http::response(['data' => [$second], 'meta' => ['last_page' => 2]]),
        ]);
        $this->artisan('interpresso:developer-download')->expectsOutput('Download finished.')->assertExitCode(0);
        $this->assertSame(['Remote first', 'Remote second'], Translation::orderBy('id')->pluck('value')->all());
        $this->assertSame(1, Language::count());
        $this->assertSame(1, (int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->url() === $translationUrl . '?page=2' && $request['api_key'] === 'integration-secret');
        if ($dbLoader) $this->assertFileDoesNotExist(app()->langPath('en/download.php'));
        else $this->assertSame(['nested' => ['first' => 'Remote first', 'second' => 'Remote second']], require app()->langPath('en/download.php'));
    }

    public static function loaderModes(): array
    {
        return ['database' => [true], 'files' => [false]];
    }

    #[Test]
    public function developer_download_rolls_back_a_failed_page_and_restores_foreign_key_checks(): void
    {
        [$language, $first] = $this->remoteData();
        $before = Translation::firstOrFail()->getAttributes();
        config(['interpresso.api_shared_api_key' => 'integration-secret', 'interpresso.main_server_domain' => 'https://source.example']);
        Http::fake([
            '*get-languages*' => Http::response(['data' => [$language]]),
            '*page=2' => Http::response([], 503),
            '*' => Http::response(['data' => [$first], 'meta' => ['last_page' => 2]]),
        ]);
        try {
            $this->artisan('interpresso:developer-download')->run();
            $this->fail('Expected page 2 to fail.');
        } catch (\Exception $error) {
            $this->assertStringContainsString("Couldn't import page -> 2", $error->getMessage());
        }
        $this->assertSame($before, Translation::firstOrFail()->getAttributes());
        $this->assertSame(1, (int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled);
        $this->assertFileDoesNotExist(app()->langPath('en/download.php'));
    }
}
