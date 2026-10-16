<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('category')->default('Szolgáltatások');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('price');
            $table->string('price_kind')->default('fixed');
            $table->unsignedInteger('buffer_before')->default(0);
            $table->unsignedInteger('buffer_after')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('service_staff', function (Blueprint $table): void {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->primary(['service_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_staff');
        Schema::dropIfExists('services');
    }
};
