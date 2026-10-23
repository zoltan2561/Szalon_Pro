<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_calendar_connections', function (Blueprint $table): void {
            $table->unique('calendar_id');
        });
    }

    public function down(): void
    {
        Schema::table('google_calendar_connections', function (Blueprint $table): void {
            $table->dropUnique(['calendar_id']);
        });
    }
};
