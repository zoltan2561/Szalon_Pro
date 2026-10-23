<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\LoyaltyEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyEntry>
 */
class LoyaltyEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['booking_id' => Booking::factory()->state(['status' => 'completed']), 'guest_id' => fn (array $attributes): int => Booking::findOrFail($attributes['booking_id'])->guest_id, 'kind' => 'earned', 'points_delta' => 1, 'description' => 'Elvégzett látogatás', 'request_key' => fake()->uuid()];
    }
}
