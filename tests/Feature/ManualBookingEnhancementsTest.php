<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\TimeBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualBookingEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_nearest_search_respects_staff_availability_and_manual_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 06:00:00', 'UTC'));
        Salon::factory()->create(['min_notice_minutes' => 0, 'horizon_days' => 7]);
        $service = Service::factory()->create();
        $staff = $service->staff()->firstOrFail();
        $owner = User::factory()->create(['role' => 'owner']);
        TimeBlock::factory()->create(['staff_id' => $staff->id, 'starts_at' => '2026-10-19 00:00:00', 'ends_at' => '2026-10-20 00:00:00']);

        $this->actingAs($owner)->get(route('admin.create', ['service_id' => $service->id, 'staff_id' => $staff->id, 'date' => '2026-10-23', 'nearest' => 1]))
            ->assertOk()->assertViewHas('date', '2026-10-20')->assertViewHas('slots', fn ($slots) => count($slots) > 0 && $slots[0]['staff_id'] === $staff->id);
        $this->actingAs($owner)->get(route('admin.create', ['service_id' => $service->id, 'staff_id' => $staff->id, 'date' => '2026-10-23']))
            ->assertOk()->assertViewHas('date', '2026-10-23');
    }

    public function test_guest_search_uses_the_existing_authorized_guest_list(): void
    {
        Salon::factory()->create();
        Service::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = Guest::factory()->create(['name' => 'Kereshet? Vend?g']);
        $this->actingAs($owner)->get(route('admin.create', ['guest_id' => $guest->id]))
            ->assertOk()->assertSee('data-guest-search', false)->assertSee('Kereshet? Vend?g')->assertSee('selected', false);
    }
}
