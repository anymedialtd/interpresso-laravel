<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('interpresso.db_connection'));
        $name = config('interpresso.table_password_reset_tokens');
        if (!$schema->hasTable($name)) {
            $schema->create($name, function (Blueprint $table): void {
                $table->string('email')->primary('ipr_email_pk');
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::connection(config('interpresso.db_connection'))
            ->dropIfExists(config('interpresso.table_password_reset_tokens'));
    }
};
