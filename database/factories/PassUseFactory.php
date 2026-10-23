<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\GuestPass;
use App\Models\PassUse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PassUse>
 */
class PassUseFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (PassUse $passUse): void {
            GuestPass::whereKey($passUse->guest_pass_id)->decrement('visits_remaining');
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['guest_pass_id' => GuestPass::factory(), 'booking_id' => function (array $attributes): int {
            $guestPass = GuestPass::findOrFail($attributes['guest_pass_id']);

            return Booking::factory()->create(['guest_id' => $guestPass->guest_id, 'service_id' => $guestPass->service_id, 'status' => 'completed'])->id;
        }];
    }
}
