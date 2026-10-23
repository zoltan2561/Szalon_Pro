<?php

namespace Database\Seeders;

use App\Models\GoogleCalendarBusy;
use App\Models\GoogleCalendarConnection;
use Illuminate\Database\Seeder;

class GoogleCalendarBusySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            $this->command?->warn('A szintetikus Google-foglaltság csak tesztkörnyezetben hozható létre.');

            return;
        }

        $connection = GoogleCalendarConnection::first() ?? GoogleCalendarConnection::factory()->create();
        GoogleCalendarBusy::factory()->create(['google_calendar_connection_id' => $connection->id]);
    }
}
