<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('guests')->whereNull('email')->exists()) {
            throw new RuntimeException('Cannot require guest email while manual guests without email exist.');
        }
        Schema::table('guests', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
        });
    }
};
