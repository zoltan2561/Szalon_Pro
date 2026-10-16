<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained();
            $table->unsignedInteger('revision');
            $table->string('kind');
            $table->string('status')->default('pending');
            $table->dateTime('scheduled_at');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('error_code')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->unique(['booking_id', 'revision', 'kind']);
            $table->index(['status', 'scheduled_at']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
