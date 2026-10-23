<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEvent;
use Illuminate\Database\Seeder;

class GoogleCalendarEventSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            $this->command?->warn('A szintetikus Google-esemény csak tesztkörnyezetben hozható létre.');

            return;
        }

        $connection = GoogleCalendarConnection::first() ?? GoogleCalendarConnection::factory()->create();
        $booking = Booking::factory()->create(['staff_id' => $connection->staff_id]);
        GoogleCalendarEvent::factory()->create(['google_calendar_connection_id' => $connection->id, 'booking_id' => $booking->id]);
    }
}
