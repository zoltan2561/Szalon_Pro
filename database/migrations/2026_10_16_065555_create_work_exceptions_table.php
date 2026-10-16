<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff');
            $table->date('date');
            $table->json('hours');
            $table->unique(['staff_id', 'date']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_exceptions');
    }
};
