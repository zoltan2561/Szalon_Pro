<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('google_calendar_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('event_id');
            $table->unsignedInteger('synced_revision')->nullable();
            $table->unique(['google_calendar_connection_id', 'booking_id'], 'google_event_booking_unique');
            $table->unique(['google_calendar_connection_id', 'event_id'], 'google_event_id_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_events');
    }
};
