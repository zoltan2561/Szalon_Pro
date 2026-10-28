<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestListTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_visit_totals_and_sorting_only_include_their_completed_bookings(): void
    {
        Salon::factory()->create(['timezone' => 'Europe/Budapest']);
        $staff = Staff::factory()->create();
        $other = Staff::factory()->create();
        $user = User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id]);
        $anna = Guest::factory()->create(['name' => 'Anna Vendég', 'phone' => '+36 30 111 2222']);
        $bela = Guest::factory()->create(['name' => 'Béla Vendég']);
        $hidden = Guest::factory()->create(['name' => 'Másik szalonvendég']);
        Booking::factory()->create(['guest_id' => $anna->id, 'staff_id' => $staff->id, 'status' => 'completed', 'starts_at' => CarbonImmutable::parse('2026-10-01T08:00:00Z')]);
        Booking::factory()->create(['guest_id' => $anna->id, 'staff_id' => $other->id, 'status' => 'completed', 'starts_at' => CarbonImmutable::parse('2026-10-15T08:00:00Z')]);
        Booking::factory()->create(['guest_id' => $anna->id, 'staff_id' => $staff->id, 'status' => 'confirmed', 'starts_at' => CarbonImmutable::parse('2026-10-20T08:00:00Z')]);
        Booking::factory()->create(['guest_id' => $bela->id, 'staff_id' => $staff->id, 'status' => 'completed', 'starts_at' => CarbonImmutable::parse('2026-10-10T08:00:00Z')]);
        Booking::factory()->create(['guest_id' => $hidden->id, 'staff_id' => $other->id, 'status' => 'completed']);

        $this->actingAs($user)->get(route('admin.guests', ['sort' => 'last_visit']))
            ->assertOk()->assertDontSee('Másik szalonvendég')->assertSee('2026. 10. 01. 10:00')->assertDontSee('2026. 10. 15. 10:00')
            ->assertViewHas('guests', function ($guests) use ($anna, $bela): bool {
                return $guests->pluck('id')->all() === [$bela->id, $anna->id]
                    && $guests->firstWhere('id', $anna->id)->visit_count === 1;
            });
        $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('admin.guests', ['q' => '301112222']))
            ->assertOk()->assertViewHas('guests', fn ($guests) => $guests->total() === 1 && $guests->first()->visit_count === 2);
    }

    public function test_paginated_search_queries_the_complete_list_and_preserves_sort(): void
    {
        Salon::factory()->create();
        Guest::factory()->count(31)->create(['name' => 'Minta vendég']);
        Guest::factory()->create(['name' => 'Zsófia keresett']);
        $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('admin.guests', ['sort' => 'name']))
            ->assertOk()->assertViewHas('guests', fn ($guests) => $guests->total() === 32 && $guests->count() === 30)->assertSee('sort=name', false);
        $this->get(route('admin.guests', ['q' => 'Zsófia', 'sort' => 'name']))
            ->assertOk()->assertSee('Zsófia keresett')->assertViewHas('guests', fn ($guests) => $guests->total() === 1);
        $this->get(route('admin.guests', ['page' => 2, 'sort' => 'name']))
            ->assertOk()->assertSee('Zsófia keresett')->assertViewHas('guests', fn ($guests) => $guests->count() === 2);
        $this->get(route('admin.guests', ['sort' => 'invalid']))->assertSessionHasErrors('sort');
    }
}
