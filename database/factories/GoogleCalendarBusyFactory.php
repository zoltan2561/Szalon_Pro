<?php

namespace Database\Factories;

use App\Models\GoogleCalendarBusy;
use App\Models\GoogleCalendarConnection;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleCalendarBusy>
 */
class GoogleCalendarBusyFactory extends Factory
{
    public function definition(): array
    {
        $start = CarbonImmutable::tomorrow('Europe/Budapest')->setTime(10, 0)->utc();

        return [
            'google_calendar_connection_id' => GoogleCalendarConnection::factory(),
            'event_key' => hash('sha256', fake()->uuid()),
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
        ];
    }
}
