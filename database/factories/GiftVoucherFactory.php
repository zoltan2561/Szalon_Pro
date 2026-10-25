<?php

namespace Database\Factories;

use App\Models\GiftVoucher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GiftVoucher>
 */
class GiftVoucherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = bin2hex(random_bytes(32));

        return ['code' => strtoupper(bin2hex(random_bytes(8))), 'public_token' => $token, 'public_token_hash' => hash('sha256', $token), 'buyer_name' => 'Minta Vásárló', 'initial_amount' => 10000, 'balance_amount' => 10000, 'expires_at' => now()->addYear(), 'issued_by_user_id' => User::factory()->state(['role' => 'owner']), 'issue_key' => fake()->uuid()];
    }
}
