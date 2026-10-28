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

class BookingListTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_search_by_guest_phone_local_day_and_status(): void
    {
        Salon::factory()->create(['timezone' => 'Europe/Budapest']);
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = Guest::factory()->create(['name' => 'Példa Anna', 'phone' => '+36 30 555 1111', 'phone_normalized' => '+36305551111']);
        $booking = Booking::factory()->create(['guest_id' => $guest->id, 'status' => 'confirmed', 'starts_at' => CarbonImmutable::parse('2026-10-24T22:30:00Z')]);
        Booking::factory()->create(['guest_id' => $guest->id, 'status' => 'cancelled', 'starts_at' => CarbonImmutable::parse('2026-10-24T22:45:00Z')]);
        Booking::factory()->create(['guest_id' => $guest->id, 'status' => 'confirmed', 'starts_at' => CarbonImmutable::parse('2026-10-25T23:30:00Z')]);

        $response = $this->actingAs($owner)->get(route('admin.bookings.index', ['q' => 'Anna', 'date' => '2026-10-25', 'status' => 'confirmed']));
        $response
            ->assertOk()->assertSee('Foglalások')->assertSee('Példa Anna')->assertSee('2026. 10. 25. 00:30')
            ->assertViewHas('bookings', fn ($bookings) => $bookings->total() === 1 && $bookings->first()->id === $booking->id);
        $this->assertMatchesRegularExpression('/data-open-more[^>]*aria-current="page"/', $response->getContent());
        $this->get(route('admin.bookings.index', ['q' => '305551111']))
            ->assertOk()->assertViewHas('bookings', fn ($bookings) => $bookings->total() === 3);
        $this->get(route('admin.bookings.index', ['status' => 'bad']))->assertSessionHasErrors('status');
    }

    public function test_staff_only_sees_own_bookings_and_other_booking_detail_is_forbidden(): void
    {
        Salon::factory()->create();
        $ownStaff = Staff::factory()->create();
        $otherStaff = Staff::factory()->create();
        $user = User::factory()->create(['role' => 'staff', 'staff_id' => $ownStaff->id]);
        Booking::factory()->create(['staff_id' => $ownStaff->id, 'guest_id' => Guest::factory()->create(['name' => 'Saját vendég'])]);
        $other = Booking::factory()->create(['staff_id' => $otherStaff->id, 'guest_id' => Guest::factory()->create(['name' => 'Másik vendég'])]);

        $this->actingAs($user)->get(route('admin.bookings.index'))->assertOk()->assertSee('Saját vendég')->assertDontSee('Másik vendég');
        $this->get(route('admin.bookings.index', ['q' => 'Másik']))->assertOk()->assertSee('Nincs a keresésnek megfelelő foglalás.');
        $this->get(route('admin.booking', $other))->assertForbidden();
    }

    public function test_pagination_preserves_filters_and_guest_cannot_open_the_list(): void
    {
        Salon::factory()->create();
        $guest = Guest::factory()->create(['name' => 'Lapozó vendég']);
        Booking::factory()->count(31)->create(['guest_id' => $guest->id, 'status' => 'confirmed']);
        $this->get(route('admin.bookings.index'))->assertRedirect(route('login'));
        $owner = User::factory()->create(['role' => 'owner']);
        $response = $this->actingAs($owner)->get(route('admin.bookings.index', ['q' => 'Lapozó', 'status' => 'confirmed']));
        $response->assertOk()->assertViewHas('bookings', fn ($bookings) => $bookings->total() === 31 && $bookings->count() === 30)
            ->assertSee('status=confirmed', false);
        $this->get(route('admin.bookings.index', ['q' => 'Lapozó', 'status' => 'confirmed', 'page' => 2]))
            ->assertOk()->assertViewHas('bookings', fn ($bookings) => $bookings->count() === 1 && $bookings->total() === 31);
    }
}
