<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->default('Blokkolt idő');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_blocks');
    }
};
