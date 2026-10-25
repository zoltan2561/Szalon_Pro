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
        Schema::create('gift_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 24)->unique();
            $table->string('public_token_hash', 64)->unique();
            $table->text('public_token');
            $table->text('buyer_name');
            $table->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('service_name')->nullable();
            $table->unsignedInteger('initial_amount');
            $table->unsignedInteger('balance_amount');
            $table->dateTime('expires_at');
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('issue_key', 36)->unique();
            $table->timestamps();
        });
        Schema::create('voucher_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gift_voucher_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->string('request_key', 36)->unique();
            $table->dateTime('reversed_at')->nullable();
            $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('gift_vouchers');
    }
};
