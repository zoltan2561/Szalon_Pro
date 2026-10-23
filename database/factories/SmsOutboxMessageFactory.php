<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\SmsOutboxMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsOutboxMessage>
 */
class SmsOutboxMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'revision' => 1,
            'kind' => 'reminder',
            'status' => 'pending',
            'scheduled_at' => now(),
        ];
    }
}
