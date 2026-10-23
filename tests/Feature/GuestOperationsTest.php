<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;

    private Staff $staff;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00+00:00'));
        Salon::factory()->create(['features' => ['waitlist' => true]]);
        $this->service = Service::factory()->create();
        $this->staff = $this->service->staff()->first();
        $this->owner = User::factory()->create(['role' => 'owner', 'can_manage_bookings' => true]);
    }

    public function test_manual_phone_or_walk_in_booking_needs_no_email_and_queues_no_email(): void
    {
        $this->actingAs($this->owner)->post(route('admin.store'), $this->manualData(['email' => null]))->assertRedirect();
        $booking = Booking::firstOrFail();

        $this->assertSame('manual', $booking->source);
        $this->assertNull($booking->guest->email);
        $this->assertSame('06301234567', $booking->guest->phone);
        $this->assertDatabaseCount('outbox_messages', 0);

        $this->actingAs($this->owner)->post(route('admin.store'), $this->manualData(['slot' => $this->slot('2026-10-19T13:00:00+02:00'), 'phone' => null]))->assertRedirect();
        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseCount('outbox_messages', 0);
    }

    public function test_public_booking_still_requires_email(): void
    {
        $this->post(route('booking.store'), $this->manualData(['email' => null, 'terms' => 1]))->assertSessionHasErrors('email');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_token_rebooking_uses_the_exact_existing_guest_and_rejects_changed_identity(): void
    {
        $first = $this->publicBooking();
        $token = $first->manage_token;

        $this->get(route('booking.rebook.form', $token))->assertOk()->assertSee($first->guest->name)->assertSee('korábbi foglalásod vendégkártyájával');
        $this->from(route('booking.rebook.form', $token))->post(route('booking.rebook.store', $token), $this->manualData(['slot' => $this->slot('2026-10-19T13:00:00+02:00'), 'name' => $first->guest->name, 'email' => 'other@example.test', 'terms' => 1]))->assertSessionHasErrors('email');
        $this->assertDatabaseCount('bookings', 1);

        $this->post(route('booking.rebook.store', $token), $this->manualData(['slot' => $this->slot('2026-10-19T13:00:00+02:00'), 'name' => $first->guest->name, 'email' => $first->guest->email, 'terms' => 1]))->assertRedirect(route('booking.success'));
        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseCount('guests', 1);
        $this->assertSame($first->guest_id, Booking::orderByDesc('id')->firstOrFail()->guest_id);
    }

    public function test_expired_rebooking_link_returns_to_regular_booking(): void
    {
        $first = $this->publicBooking();
        $this->travelTo(CarbonImmutable::parse('2026-11-20T06:00:00+00:00'));

        $this->get(route('booking.rebook.form', $first->manage_token))->assertRedirect(route('booking.form'))->assertSessionHas('error');
        $this->post(route('booking.rebook.store', $first->manage_token), $this->manualData(['terms' => 1]))->assertRedirect(route('booking.form'));
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_waitlist_can_create_phone_contact_and_convert_once_into_a_booking(): void
    {
        $this->actingAs($this->owner)->post(route('admin.waitlist.store'), [
            'name' => 'Várakozó Vendég', 'phone' => '06305554444', 'service_id' => $this->service->id,
            'staff_id' => $this->staff->id, 'preferred_date' => '2026-10-19',
        ])->assertRedirect(route('admin.waitlist.index'));
        $entry = WaitlistEntry::with('guest')->firstOrFail();
        $this->assertNull($entry->guest->email);
        $this->actingAs($this->owner)->get(route('admin.waitlist.index'))->assertOk()->assertSee('Várakozó Vendég');

        $data = $this->manualData(['guest_id' => $entry->guest_id, 'name' => null, 'email' => null, 'phone' => null, 'waitlist_entry_id' => $entry->id]);
        $this->actingAs($this->owner)->post(route('admin.store'), $data)->assertRedirect();
        $this->assertSame('booked', $entry->fresh()->status);
        $this->assertSame(Booking::firstOrFail()->id, $entry->fresh()->booked_booking_id);
        $this->assertSame($entry->guest_id, Booking::firstOrFail()->guest_id);
        $this->assertDatabaseCount('guests', 1);

        $this->actingAs($this->owner)->post(route('admin.store'), array_replace($data, ['request_key' => Str::uuid()->toString(), 'slot' => $this->slot('2026-10-19T13:00:00+02:00')]))->assertSessionHasErrors('waitlist_entry_id');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_waitlist_is_hidden_when_module_is_off_and_staff_cannot_read_other_staff_entries(): void
    {
        Salon::current()->update(['features' => ['waitlist' => false]]);
        $this->actingAs($this->owner)->get(route('admin.waitlist.index'))->assertNotFound();

        Salon::current()->update(['features' => ['waitlist' => true]]);
        $otherStaff = Staff::factory()->create();
        $staffUser = User::factory()->create(['role' => 'staff', 'staff_id' => $this->staff->id, 'can_manage_bookings' => true]);
        WaitlistEntry::factory()->create(['guest_id' => Guest::factory()->create(['name' => 'Saját Vendég'])->id, 'service_id' => $this->service->id, 'staff_id' => $this->staff->id]);
        $other = WaitlistEntry::factory()->create(['guest_id' => Guest::factory()->create(['name' => 'Másik Vendég'])->id, 'service_id' => $this->service->id, 'staff_id' => $otherStaff->id]);

        $this->actingAs($staffUser)->get(route('admin.waitlist.index'))->assertOk()->assertSee('Saját Vendég')->assertDontSee('Másik Vendég');
        $this->actingAs($staffUser)->post(route('admin.waitlist.cancel', $other))->assertForbidden();
        $this->assertSame('waiting', $other->fresh()->status);
    }

    public function test_staff_can_convert_their_new_waitlist_contact_without_access_to_other_guest_cards(): void
    {
        $staffUser = User::factory()->create(['role' => 'staff', 'staff_id' => $this->staff->id, 'can_manage_bookings' => true]);
        $this->actingAs($staffUser)->post(route('admin.waitlist.store'), [
            'name' => 'Első várólistás', 'phone' => '06307778888', 'service_id' => $this->service->id,
        ])->assertRedirect(route('admin.waitlist.index'));
        $entry = WaitlistEntry::firstOrFail();

        $this->actingAs($staffUser)->get(route('admin.create', ['guest_id' => $entry->guest_id, 'service_id' => $this->service->id, 'waitlist_entry_id' => $entry->id]))
            ->assertOk()->assertSee('Első várólistás');
        $this->actingAs($staffUser)->post(route('admin.store'), $this->manualData([
            'guest_id' => $entry->guest_id, 'name' => null, 'email' => null, 'phone' => null, 'waitlist_entry_id' => $entry->id,
        ]))->assertRedirect();
        $this->assertSame('booked', $entry->fresh()->status);
    }

    private function manualData(array $overrides = []): array
    {
        return array_replace([
            'service_id' => $this->service->id, 'slot' => $this->slot('2026-10-19T10:00:00+02:00'),
            'name' => 'Telefonos Vendég', 'email' => null, 'phone' => '06301234567', 'request_key' => Str::uuid()->toString(),
        ], $overrides);
    }

    private function slot(string $start): string
    {
        return $this->staff->id.'|'.$start;
    }

    private function publicBooking(): Booking
    {
        return app(BookingService::class)->create([
            'service_id' => $this->service->id, 'staff_id' => $this->staff->id,
            'starts_at' => '2026-10-19T10:00:00+02:00', 'name' => 'Visszatérő Vendég',
            'email' => 'visszatero@example.test', 'phone' => null, 'request_key' => Str::uuid()->toString(),
        ]);
    }
}
