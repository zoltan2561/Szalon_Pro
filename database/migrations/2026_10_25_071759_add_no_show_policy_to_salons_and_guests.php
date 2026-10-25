<?php

use App\Services\SmsGateway;
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
        Schema::table('salons', function (Blueprint $table): void {
            $table->unsignedInteger('no_show_limit')->default(0);
        });
        Schema::table('guests', function (Blueprint $table): void {
            $table->string('phone_normalized', 20)->nullable()->index();
        });
        DB::table('guests')->orderBy('id')->chunkById(100, function ($guests): void {
            foreach ($guests as $guest) {
                DB::table('guests')->where('id', $guest->id)->update(['phone_normalized' => app(SmsGateway::class)->normalizeNumber($guest->phone)]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salons', fn (Blueprint $table) => $table->dropColumn('no_show_limit'));
        Schema::table('guests', fn (Blueprint $table) => $table->dropColumn('phone_normalized'));
    }
};
