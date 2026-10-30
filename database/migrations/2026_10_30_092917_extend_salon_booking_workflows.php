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
        Schema::table('bookings', function (Blueprint $table): void {
            $table->json('service_items')->nullable();
            $table->uuid('series_key')->nullable()->index();
            $table->unsignedTinyInteger('series_position')->nullable();
            $table->unsignedTinyInteger('series_interval_weeks')->nullable();
            $table->json('work_notes')->nullable();
            $table->json('work_photos')->nullable();
            $table->unsignedInteger('notes_revision')->default(1);
            $table->timestamp('notes_updated_at')->nullable();
            $table->foreignId('notes_updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
        });
        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff');
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('note', 255)->nullable();
            $table->string('review_note', 255)->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('work_exceptions', function (Blueprint $table): void {
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('waitlist_entries', function (Blueprint $table): void {
            $table->date('preferred_until')->nullable();
            $table->string('time_from', 5)->nullable();
            $table->string('time_until', 5)->nullable();
            $table->string('token_hash', 64)->nullable()->unique();
            $table->text('manage_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('notification_consent_at')->nullable();
            $table->timestamp('last_offered_at')->nullable();
        });
        Schema::create('waitlist_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('waitlist_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff');
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->string('token_hash', 64)->unique();
            $table->text('manage_token');
            $table->string('channel')->default('email');
            $table->string('status')->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();
            $table->unique(['waitlist_entry_id', 'starts_at', 'staff_id'], 'waitlist_offer_slot_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waitlist_offers');
        Schema::table('waitlist_entries', function (Blueprint $table): void {
            $table->dropUnique(['token_hash']);
            $table->dropColumn(['preferred_until', 'time_from', 'time_until', 'token_hash', 'manage_token', 'token_expires_at', 'notification_consent_at', 'last_offered_at']);
        });
        Schema::table('work_exceptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('leave_request_id');
        });
        Schema::dropIfExists('leave_requests');
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('notes_updated_by_id');
            $table->dropIndex(['series_key']);
            $table->dropColumn(['service_items', 'series_key', 'series_position', 'series_interval_weeks', 'work_notes', 'work_photos', 'notes_revision', 'notes_updated_at', 'arrived_at', 'started_at']);
        });
    }
};
