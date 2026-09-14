<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if(!Schema::connection(config('interpresso.db_connection'))->hasColumns(config('interpresso.table_settings'), ['enable_open_ai_translations'])) {
            Schema::connection(config('interpresso.db_connection'))->table(config('interpresso.table_settings'), function (Blueprint $table) {
                $table->boolean('enable_open_ai_translations')->default(false)->after('import_vendor');
            });
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if(Schema::connection(config('interpresso.db_connection'))->hasColumns(config('interpresso.table_settings'), ['enable_open_ai_translations'])) {
            Schema::connection(config('interpresso.db_connection'))->table(config('interpresso.table_settings'), function (Blueprint $table) {
                Schema::connection(config('interpresso.db_connection'))->dropColumns(config('interpresso.table_settings'), ['enable_open_ai_translations']);
            });
        }
    }
};
