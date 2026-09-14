<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('interpresso.table_settings');
        $schema = Schema::connection(config('interpresso.db_connection'));
        if (!$schema->hasTable($tableName)) {
            return;
        }

        foreach (['process_owner', 'process_started_at', 'process_expires_at'] as $column) {
            if (!$schema->hasColumn($tableName, $column)) {
                $schema->table($tableName, function (Blueprint $table) use ($column): void {
                    if ($column === 'process_owner') {
                        $table->string($column)->nullable();
                    } else {
                        $table->timestamp($column)->nullable();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        $tableName = config('interpresso.table_settings');
        $schema = Schema::connection(config('interpresso.db_connection'));
        if (!$schema->hasTable($tableName)) {
            return;
        }

        foreach (['process_owner', 'process_started_at', 'process_expires_at'] as $column) {
            if ($schema->hasColumn($tableName, $column)) {
                $schema->table($tableName, fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
