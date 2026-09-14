<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('interpresso.table_translators');
        $schema = Schema::connection(config('interpresso.db_connection'));
        if ($schema->hasTable($tableName) && !$schema->hasColumn($tableName, 'locale')) {
            $schema->table($tableName, fn (Blueprint $table) => $table->string('locale')->nullable());
        }
    }

    public function down(): void
    {
        $tableName = config('interpresso.table_translators');
        $schema = Schema::connection(config('interpresso.db_connection'));
        if ($schema->hasTable($tableName) && $schema->hasColumn($tableName, 'locale')) {
            $schema->table($tableName, fn (Blueprint $table) => $table->dropColumn('locale'));
        }
    }
};
