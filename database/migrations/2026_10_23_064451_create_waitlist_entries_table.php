<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->date('preferred_date')->nullable();
            $table->string('note', 255)->nullable();
            $table->string('status')->default('waiting');
            $table->foreignId('booked_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'preferred_date']);
            $table->index(['staff_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
