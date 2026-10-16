<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Service;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $token = bin2hex(random_bytes(32));
        $start = CarbonImmutable::tomorrow('Europe/Budapest')->setTime(10, 0)->utc();

        return ['service_id' => Service::factory(), 'staff_id' => Staff::factory(), 'guest_id' => Guest::factory(), 'service_name' => 'Teszt szolgáltatás', 'duration_minutes' => 60, 'price' => 9500, 'price_kind' => 'fixed', 'buffer_before' => 0, 'buffer_after' => 15, 'starts_at' => $start, 'ends_at' => $start->addHour(), 'busy_from' => $start, 'busy_until' => $start->addMinutes(75), 'status' => 'confirmed', 'source' => 'online', 'request_key' => fake()->uuid(), 'request_hash' => hash('sha256', fake()->uuid()), 'token_hash' => hash('sha256', $token), 'manage_token' => $token, 'token_expires_at' => $start->addDays(30)];
    }
}
