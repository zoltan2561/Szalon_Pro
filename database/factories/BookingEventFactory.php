<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingEventFactory extends Factory
{
    protected $model = BookingEvent::class;

    public function definition(): array
    {
        return ['booking_id' => Booking::factory(), 'action' => 'created', 'changes' => []];
    }
}
