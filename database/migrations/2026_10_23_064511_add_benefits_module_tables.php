<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->unsignedSmallInteger('loyalty_points')->nullable();
        });

        Schema::create('benefit_settings', function (Blueprint $table): void {
            $table->foreignId('salon_id')->primary()->constrained('salons')->cascadeOnDelete();
            $table->unsignedSmallInteger('loyalty_points_per_visit')->default(1);
            $table->unsignedSmallInteger('loyalty_reward_threshold')->default(10);
            $table->string('loyalty_reward_label')->default('Hűségjutalom');
            $table->timestamps();
        });

        Schema::create('pass_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('visits_total');
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('guest_passes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->foreignId('pass_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('visits_total');
            $table->unsignedSmallInteger('visits_remaining');
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->string('issue_key', 64)->unique();
            $table->timestamps();
            $table->index(['guest_id', 'service_id']);
        });

        Schema::create('pass_uses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guest_pass_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->dateTime('reversed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('loyalty_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 24);
            $table->integer('points_delta');
            $table->string('description');
            $table->string('request_key', 100)->unique();
            $table->timestamps();
            $table->unique(['booking_id', 'kind']);
            $table->index(['guest_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_entries');
        Schema::dropIfExists('pass_uses');
        Schema::dropIfExists('guest_passes');
        Schema::dropIfExists('pass_plans');
        Schema::dropIfExists('benefit_settings');

        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('loyalty_points');
        });
    }
};
