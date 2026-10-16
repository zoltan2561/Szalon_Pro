<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('title')->nullable();
            $table->text('bio')->nullable();
            $table->boolean('active')->default(true);
            $table->json('weekly_hours');
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('staff');
            $table->foreignId('staff_id')->nullable()->constrained('staff');
            $table->boolean('can_manage_bookings')->default(false);
            $table->boolean('can_view_notes')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('staff_id');
            $table->dropColumn(['role', 'can_manage_bookings', 'can_view_notes']);
        });
        Schema::dropIfExists('staff');
    }
};
