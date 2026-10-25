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
        Schema::table('salons', function (Blueprint $table): void {
            $table->string('review_url', 1000)->nullable();
            $table->unsignedInteger('review_delay_hours')->default(3);
        });
        Schema::table('guests', function (Blueprint $table): void {
            $table->dateTime('review_opt_in_at')->nullable();
            $table->dateTime('review_opt_out_at')->nullable();
            $table->dateTime('last_review_requested_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salons', fn (Blueprint $table) => $table->dropColumn(['review_url', 'review_delay_hours']));
        Schema::table('guests', fn (Blueprint $table) => $table->dropColumn(['review_opt_in_at', 'review_opt_out_at', 'last_review_requested_at']));
    }
};
