<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use AnyMedia\Interpresso\Languages;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        if(!Schema::connection(config('interpresso.db_connection'))->hasTable(config('interpresso.table_languages'))) {
            Schema::connection(config('interpresso.db_connection'))->create(config('interpresso.table_languages'), function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('native_name');
                $table->string('code')->unique();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        if(Schema::connection(config('interpresso.db_connection'))->hasTable(config('interpresso.table_languages'))) {
            Schema::connection(config('interpresso.db_connection'))->dropIfExists(config('interpresso.table_languages'));
        }
    }
};
