<?php

namespace Database\Factories;

use App\Models\WaitlistEntry;
use App\Models\WaitlistOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistOffer>
 */
class WaitlistOfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = bin2hex(random_bytes(32));

        return [
            'waitlist_entry_id' => WaitlistEntry::factory(),
            'staff_id' => fn (array $attributes): int => WaitlistEntry::findOrFail($attributes['waitlist_entry_id'])->service->staff()->firstOrFail()->id,
            'starts_at' => now('Europe/Budapest')->addDay()->setTime(10, 0)->utc(),
            'expires_at' => now()->addMinutes(30),
            'token_hash' => hash('sha256', $token),
            'manage_token' => $token,
            'channel' => 'email', 'status' => 'pending', 'scheduled_at' => now(),
        ];
    }
}
