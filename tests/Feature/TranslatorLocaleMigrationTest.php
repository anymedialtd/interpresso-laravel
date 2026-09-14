<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Tests\BaseTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class TranslatorLocaleMigrationTest extends BaseTestCase
{
    use RefreshDatabase;

    #[Test]
    public function locale_is_nullable_without_a_backfill_and_migration_guards_respect_connection_and_table_config(): void
    {
        $this->assertNull(Translator::firstOrFail()->locale);
        config([
            'database.connections.locale_migration' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'interpresso.db_connection' => 'locale_migration', 'interpresso.table_translators' => 'custom_translators',
        ]);
        $schema = Schema::connection('locale_migration');
        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_09_14_000001_add_locale_to_translators_table.php';
        $migration->up();
        $migration->down();
        $this->assertFalse($schema->hasTable('custom_translators'));
        $schema->create('custom_translators', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
        });
        $table = DB::connection('locale_migration')->table('custom_translators');
        $table->insert(['email' => 'existing@example.test']);
        $migration->up();
        $migration->up();
        $this->assertNull((clone $table)->value('locale'));
        $table->update(['locale' => 'fr']);
        $migration->up();
        $this->assertSame('fr', (clone $table)->value('locale'));
        $migration->down();
        $migration->down();
        $this->assertFalse($schema->hasColumn('custom_translators', 'locale'));
        $this->assertSame('existing@example.test', (clone $table)->value('email'));
        $this->assertTrue(Schema::connection('testbench')->hasColumn('interpresso_translators', 'locale'));
        $migration->up();
        $this->assertNull((clone $table)->value('locale'));
    }
}
