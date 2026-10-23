<?php

namespace Database\Seeders;

use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\WaitlistEntry;
use Illuminate\Database\Seeder;

class WaitlistEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Salon::find(1)?->demo) {
            return;
        }
        $guest = Guest::where('email', 'guest@example.test')->first();
        $service = Service::where('active', true)->first();
        if (! $guest || ! $service) {
            return;
        }

        WaitlistEntry::firstOrCreate(
            ['guest_id' => $guest->id, 'service_id' => $service->id, 'status' => 'waiting'],
            ['staff_id' => $service->staff()->where('active', true)->first()?->id, 'note' => 'Bemutató várólistás kérés'],
        );
    }
}
