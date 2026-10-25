<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\FiscalDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalDocument>
 */
class FiscalDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['booking_id' => Booking::factory()->state(['status' => 'completed']), 'created_by_user_id' => User::factory()->state(['role' => 'owner']), 'kind' => 'invoice', 'status' => 'pending', 'external_id' => 'sp-'.fake()->uuid(), 'payload' => ['service_name' => 'Minta szolgáltatás', 'gross' => 10000, 'net' => '10000.00', 'tax' => '0.00', 'vat' => 'AAM', 'prefix' => 'NY', 'issue_date' => now()->toDateString(), 'performance_date' => now()->toDateString(), 'payment_method' => 'készpénz', 'buyer_name' => 'Minta', 'buyer_postcode' => '1111', 'buyer_city' => 'Budapest', 'buyer_address' => 'Minta utca 1.']];
    }
}
