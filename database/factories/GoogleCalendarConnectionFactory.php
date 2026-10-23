<?php

namespace Database\Factories;

use App\Models\GoogleCalendarConnection;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleCalendarConnection>
 */
class GoogleCalendarConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'staff_id' => Staff::factory(),
            'calendar_id' => fake()->safeEmail(),
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
            'last_imported_at' => now(),
            'sync_status' => 'connected',
        ];
    }
}
