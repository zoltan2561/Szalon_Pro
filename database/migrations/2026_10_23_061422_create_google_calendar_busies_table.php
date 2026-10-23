<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_busies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('google_calendar_connection_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 64);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unique(['google_calendar_connection_id', 'event_key'], 'google_busy_event_unique');
            $table->index(['google_calendar_connection_id', 'starts_at', 'ends_at'], 'google_busy_interval_index');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_busies');
    }
};
