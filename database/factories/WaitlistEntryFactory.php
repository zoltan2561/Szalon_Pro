<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\Service;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['guest_id' => Guest::factory(), 'service_id' => Service::factory(), 'staff_id' => null, 'preferred_date' => null, 'status' => 'waiting'];
    }
}
