<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->string('service_name');
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('price');
            $table->string('price_kind');
            $table->unsignedInteger('buffer_before');
            $table->unsignedInteger('buffer_after');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('busy_from');
            $table->dateTime('busy_until');
            $table->string('status')->default('confirmed');
            $table->string('source');
            $table->unsignedInteger('actual_amount')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->string('request_key', 100)->unique();
            $table->string('request_hash', 64);
            $table->string('token_hash', 64)->unique();
            $table->text('manage_token');
            $table->dateTime('token_expires_at');
            $table->index(['staff_id', 'busy_from', 'busy_until']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
