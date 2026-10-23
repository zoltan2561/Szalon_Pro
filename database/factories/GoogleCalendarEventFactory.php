<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleCalendarEvent>
 */
class GoogleCalendarEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'google_calendar_connection_id' => GoogleCalendarConnection::factory(),
            'booking_id' => Booking::factory(),
            'event_id' => 'sp'.bin2hex(random_bytes(24)),
            'synced_revision' => 1,
        ];
    }
}
