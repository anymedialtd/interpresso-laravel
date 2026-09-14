<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use AnyMedia\Interpresso\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if(!Schema::connection(config('interpresso.db_connection'))->hasTable(config('interpresso.table_settings'))) {
            Schema::connection(config('interpresso.db_connection'))->create(config('interpresso.table_settings'), function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->boolean('db_loader')->default(true);
                $table->boolean('import_vendor')->default(false);
                $table->timestamps();
            });

            /**
             * Creates first setting
             */
            if(!Setting::query()->first()) {
                Setting::query()->create(
                    ['db_loader' => true]
                );
            }
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if(Schema::connection(config('interpresso.db_connection'))->hasTable(config('interpresso.table_settings'))) {
            Schema::connection(config('interpresso.db_connection'))->dropIfExists(config('interpresso.table_settings'));
        }
    }
};
