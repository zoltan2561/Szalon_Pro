<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salons', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->default('SzalonPRO');
            $table->string('tagline')->default('Idő magadra.');
            $table->text('about')->nullable();
            $table->string('email')->default('hello@example.test');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('timezone')->default('Europe/Budapest');
            $table->string('theme')->default('salon');
            $table->string('accent')->default('#765b55');
            $table->string('font')->default('serif');
            $table->string('logo')->nullable();
            $table->json('gallery')->nullable();
            $table->json('opening_hours')->nullable();
            $table->text('terms')->nullable();
            $table->text('privacy')->nullable();
            $table->boolean('published')->default(false);
            $table->boolean('demo')->default(false);
            $table->unsignedInteger('min_notice_minutes')->default(120);
            $table->unsignedInteger('horizon_days')->default(60);
            $table->unsignedInteger('cancel_notice_hours')->default(24);
            $table->unsignedInteger('reminder_hours')->default(24);
            $table->unsignedInteger('retention_days')->default(730);
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salons');
    }
};
