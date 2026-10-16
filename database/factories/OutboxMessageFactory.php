<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\OutboxMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class OutboxMessageFactory extends Factory
{
    protected $model = OutboxMessage::class;

    public function definition(): array
    {
        return ['booking_id' => Booking::factory(), 'revision' => 1, 'kind' => 'confirmation', 'scheduled_at' => now()];
    }
}
