<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use AnyMedia\Interpresso\Tests\BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class PasswordResetMigrationTest extends BaseTestCase
{
    use RefreshDatabase;

    #[Test]
    public function reset_table_uses_the_package_connection_and_custom_name_and_preserves_existing_tokens(): void
    {
        config([
            'database.connections.password_migration' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'interpresso.db_connection' => 'password_migration',
            'interpresso.table_password_reset_tokens' => 'custom_translator_password_reset_tokens_with_a_long_table_name',
        ]);
        $schema = Schema::connection('password_migration');
        $name = config('interpresso.table_password_reset_tokens');
        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_09_14_000002_create_translator_password_reset_tokens_table.php';
        $migration->up();
        $table = DB::connection('password_migration')->table($name);
        $table->insert(['email' => 'test@example.test', 'token' => 'existing-hash', 'created_at' => now()]);
        $migration->up();
        $this->assertSame('existing-hash', (clone $table)->value('token'));
        $this->assertFalse(Schema::connection('testbench')->hasTable($name));
        // SQLite invents its own sqlite_autoindex_* names for primary keys.
        // Identifier lengths must be checked on the deployment engine instead.
        $this->assertTrue($schema->hasIndex($name, ['email'], 'primary'));
        $migration->down();
        $migration->down();
        $this->assertFalse($schema->hasTable($name));
        $migration->up();
        $this->assertTrue($schema->hasColumns($name, ['email', 'token', 'created_at']));
    }
}
