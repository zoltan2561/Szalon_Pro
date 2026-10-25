<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Services\NoShowPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NoShowPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_threshold_blocks_online_matching_contact_but_owner_can_still_book(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        $salon = Salon::factory()->create(['features' => ['no_show_protection' => true], 'no_show_limit' => 1]);
        $service = Service::factory()->create();
        $staff = $service->staff()->first();
        $guest = Guest::factory()->create(['email' => 'guest@example.test', 'phone' => '06301234567']);
        Booking::factory()->create(['guest_id' => $guest->id, 'service_id' => $service->id, 'staff_id' => $staff->id, 'status' => 'no_show', 'busy_from' => '2026-10-15 08:00:00', 'busy_until' => '2026-10-15 09:00:00']);
        $data = ['service_id' => $service->id, 'slot' => $staff->id.'|2026-10-19T10:00:00+02:00', 'name' => 'Minta', 'email' => 'GUEST@example.test', 'phone' => '+36 30 123 4567', 'terms' => 1, 'request_key' => Str::uuid()->toString()];
        $this->postJson(route('booking.store'), $data)->assertUnprocessable()->assertSee('telefonon');
        $this->assertDatabaseCount('bookings', 1);
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->post(route('admin.store'), $data + ['guest_id' => $guest->id])->assertRedirect();
        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseCount('guests', 1);
        $this->get(route('admin.guest', $guest))->assertOk()->assertSee('Nem jelent meg:');
        $salon->update(['features' => []]);
        $this->assertFalse(app(NoShowPolicy::class)->blocks($data));
    }

    public function test_only_owner_can_change_policy(): void
    {
        Salon::factory()->create(['features' => ['no_show_protection' => true]]);
        $service = Service::factory()->create();
        $staff = User::factory()->create(['role' => 'staff', 'staff_id' => $service->staff()->first()->id]);
        $this->actingAs($staff)->post(route('admin.policy.no-show'), ['no_show_limit' => 2])->assertForbidden();
    }
}
