<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Services\Availability;
use App\Services\BookingService;
use App\Services\ServiceSelection;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CombinedServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_combined_services_share_one_qualified_staff_member_and_reserve_the_whole_duration(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19T06:00:00Z'));
        Salon::factory()->create();
        $primary = Service::factory()->create(['duration_minutes' => 60, 'price' => 9000, 'buffer_before' => 0, 'buffer_after' => 15]);
        $staff = $primary->staff->first();
        $extra = Service::factory()->create(['duration_minutes' => 30, 'price' => 3000, 'buffer_before' => 5, 'buffer_after' => 10]);
        $extra->staff()->sync([$staff->id]);
        $selection = app(ServiceSelection::class)->combine($primary, [$extra->id]);
        $this->assertSame(110, $selection->duration_minutes);
        $this->assertSame(12000, $selection->price);
        $this->assertFalse(app(Availability::class)->canBook($selection, Staff::factory()->create(), CarbonImmutable::parse('2026-10-19T08:00:00Z')));
        $booking = app(BookingService::class)->create(['service_id' => $primary->id, 'extra_service_ids' => [$extra->id], 'staff_id' => $staff->id, 'starts_at' => '2026-10-19T10:00:00+02:00', 'name' => 'Kombinált vendég', 'email' => 'combo@example.test', 'request_key' => Str::uuid()->toString()]);
        $this->assertCount(2, $booking->service_items);
        $this->assertSame('2026-10-19 10:00:00', $booking->busy_until->format('Y-m-d H:i:s'));
        $this->assertSame(12000, $booking->price);
        $this->assertFalse(app(Availability::class)->canBook($primary, $staff, CarbonImmutable::parse('2026-10-19T09:30:00Z')));
    }

    public function test_a_service_combination_with_no_common_staff_exposes_no_slots(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19T06:00:00Z'));
        Salon::factory()->create();
        $primary = Service::factory()->create();
        $extra = Service::factory()->create();
        $this->get(route('booking.form', ['service_id' => $primary->id, 'extra_service_ids' => [$extra->id], 'date' => '2026-10-19']))->assertOk()
            ->assertViewHas('slots', [])->assertSee('Ehhez a kombinációhoz nincs közös szakember.');
    }

    public function test_service_snapshots_and_duration_survive_catalog_changes_when_moving(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19T06:00:00Z'));
        Salon::factory()->create();
        $primary = Service::factory()->create();
        $extra = Service::factory()->create();
        $staff = $primary->staff->first();
        $extra->staff()->sync([$staff->id]);
        $booking = app(BookingService::class)->create(['service_id' => $primary->id, 'extra_service_ids' => [$extra->id], 'staff_id' => $staff->id, 'starts_at' => '2026-10-19T10:00:00+02:00', 'name' => 'Teszt', 'email' => 'test@example.test', 'request_key' => Str::uuid()->toString()]);
        $extra->update(['duration_minutes' => 15, 'price' => 1000]);
        $selection = app(ServiceSelection::class)->forBooking($booking);
        $this->assertSame($booking->duration_minutes, $selection->duration_minutes);
        $this->assertSame(9500, $booking->service_items[1]['price']);
        $this->get(route('booking.manage', $booking->manage_token))->assertOk();
        $this->get(route('booking.rebook.form', $booking->manage_token))->assertOk()
            ->assertViewHas('service', fn ($service): bool => $service->selectedServices->count() === 2);
        $this->get(route('booking.rebook.form', ['token' => $booking->manage_token, 'custom_services' => 1]))->assertOk()
            ->assertViewHas('service', fn ($service): bool => $service->duration_minutes === $primary->duration_minutes);
    }
}
