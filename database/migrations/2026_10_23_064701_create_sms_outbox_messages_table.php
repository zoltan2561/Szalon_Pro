<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_outbox_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('kind', 24);
            $table->string('status', 24)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('scheduled_at');
            $table->dateTime('sent_at')->nullable();
            $table->string('provider_id', 64)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'revision', 'kind']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_outbox_messages');
    }
};
