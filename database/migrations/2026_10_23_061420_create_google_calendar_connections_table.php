<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_id')->unique()->constrained('staff')->cascadeOnDelete();
            $table->string('calendar_id')->default('primary');
            $table->text('access_token');
            $table->text('refresh_token');
            $table->dateTime('token_expires_at');
            $table->dateTime('last_imported_at')->nullable();
            $table->dateTime('last_exported_at')->nullable();
            $table->string('sync_status')->default('pending');
            $table->string('last_error_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_connections');
    }
};
