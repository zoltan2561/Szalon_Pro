<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use App\Models\TimeBlock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicAvailabilityRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_availability_can_refresh_across_booking_and_rescheduling_without_changing_the_booking(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        Salon::factory()->create();
        $service = Service::factory()->create();
        $staff = $service->staff()->first();
        $this->post(route('booking.store'), [
            'service_id' => $service->id,
            'slot' => $staff->id.'|2026-10-19T10:00:00+02:00',
            'name' => 'Teszt Vendég',
            'email' => 'guest@example.test',
            'terms' => 1,
            'request_key' => Str::uuid()->toString(),
        ])->assertRedirect(route('booking.success'));
        $booking = Booking::firstOrFail();
        TimeBlock::factory()->create([
            'staff_id' => $staff->id,
            'starts_at' => '2026-10-20 07:00:00',
            'ends_at' => '2026-10-20 16:00:00',
        ]);

        foreach ([route('booking.form'), route('booking.rebook.form', $booking->manage_token), route('booking.manage', $booking->manage_token)] as $url) {
            $this->get($url.'?'.http_build_query(['service_id' => $service->id, 'date' => '2026-10-19']))
                ->assertOk()->assertSee('data-availability-filter', false)->assertSee('data-availability-submit', false)
                ->assertSee('2026-10-19T12:00:00+02:00');
            $this->get($url.'?'.http_build_query(['service_id' => $service->id, 'date' => '2026-10-20']))
                ->assertOk()->assertSee('Erre a napra nincs szabad időpont.');
        }

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(1, $booking->fresh()->revision);
        $this->assertDatabaseCount('bookings', 1);
    }
}
