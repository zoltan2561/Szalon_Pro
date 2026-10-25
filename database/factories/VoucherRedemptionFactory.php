<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\GiftVoucher;
use App\Models\User;
use App\Models\VoucherRedemption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoucherRedemption>
 */
class VoucherRedemptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['gift_voucher_id' => GiftVoucher::factory(), 'booking_id' => Booking::factory()->state(['status' => 'completed']), 'amount' => 1000, 'request_key' => fake()->uuid(), 'created_by_user_id' => User::factory()->state(['role' => 'owner'])];
    }
}
