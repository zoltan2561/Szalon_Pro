<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('action');
            $table->json('changes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_events');
    }
};
