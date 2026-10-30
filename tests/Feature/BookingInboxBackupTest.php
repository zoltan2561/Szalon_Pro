<?php

namespace Tests\Feature;

use App\Console\Commands\BackupSalon;
use App\Models\Booking;
use App\Models\Salon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingInboxBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_version_four_backup_still_restores_into_the_current_schema(): void
    {
        Salon::factory()->create();
        $booking = Booking::factory()->create();
        $path = storage_path('framework/inbox-legacy-'.Str::uuid().'.backup');
        try {
            $this->artisan('salon:backup', ['--path' => $path])->assertSuccessful();
            $snapshot = json_decode(Crypt::decryptString(file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);
            $snapshot['format'] = 4;
            unset($snapshot['tables']['notifications']);
            file_put_contents($path, Crypt::encryptString(json_encode($snapshot, JSON_THROW_ON_ERROR)));
            foreach (array_reverse(BackupSalon::TABLES) as $table) {
                DB::table($table)->delete();
            }

            $this->artisan('salon:restore', ['path' => $path])->assertSuccessful();
            $this->assertSame($booking->manage_token, Booking::findOrFail($booking->id)->manage_token);
            $this->assertDatabaseCount('notifications', 0);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
