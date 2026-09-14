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
        if(!Schema::connection(config('interpresso.db_connection'))->hasColumns(config('interpresso.table_settings'), ['enable_pending_notifications', 'enable_automatic_pending_notifications'])) {
            Schema::connection(config('interpresso.db_connection'))->table(config('interpresso.table_settings'), function (Blueprint $table) {
                $table->boolean('enable_pending_notifications')->default(false)->after('import_vendor');
                $table->boolean('enable_automatic_pending_notifications')->default(false)->after('enable_pending_notifications');
            });
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if(Schema::connection(config('interpresso.db_connection'))->hasColumns(config('interpresso.table_settings'), ['enable_pending_notifications', 'enable_automatic_pending_notifications'])) {
            Schema::connection(config('interpresso.db_connection'))->table(config('interpresso.table_settings'), function (Blueprint $table) {
                Schema::connection(config('interpresso.db_connection'))->dropColumns(config('interpresso.table_settings'), ['enable_pending_notifications', 'enable_automatic_pending_notifications']);
            });
        }
    }
};
