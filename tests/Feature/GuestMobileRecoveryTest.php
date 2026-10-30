<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GuestMobileRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19T06:00:00Z'));
        Salon::factory()->create(['features' => ['waitlist' => true, 'phone_booking' => true, 'sms' => true]]);
        config(['services.sms' => ['provider' => 'twilio', 'account_sid' => 'AC'.str_repeat('a', 32), 'auth_token' => 'fake', 'sender' => '+36111111111']]);
    }

    public function test_waitlist_form_accepts_phone_only_when_sms_is_ready_and_retains_failed_input(): void
    {
        $service = Service::factory()->create();
        $response = $this->get(route('waitlist.join', ['service_id' => $service->id]))->assertOk();
        $this->assertFalse($this->input($response->getContent(), 'email')->hasAttribute('required'));
        $response->assertSee('E-mail vagy telefonszám');
        $data = $this->waitlistData($service) + ['phone' => '+36301234567'];
        $this->post(route('waitlist.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull(WaitlistEntry::sole()->guest->email);
        $this->assertSame('+36301234567', WaitlistEntry::sole()->guest->phone);
        $this->assertDatabaseCount('bookings', 0);

        $this->from(route('waitlist.join'))->post(route('waitlist.store'), array_replace($data, ['phone' => 'invalid']))->assertSessionHasErrors('phone');
        $retry = $this->get(route('waitlist.join'))->assertOk();
        $this->assertSame('invalid', $this->input($retry->getContent(), 'phone')->getAttribute('value'));
        $this->assertSame('Várakozó vendég', $this->input($retry->getContent(), 'name')->getAttribute('value'));
    }

    /** @return array<string, array{string}> */
    public static function unavailablePhoneChannels(): array
    {
        return ['feature off' => ['phone_booking'], 'SMS off' => ['sms'], 'unconfigured gateway' => ['gateway']];
    }

    #[DataProvider('unavailablePhoneChannels')]
    public function test_waitlist_requires_email_when_the_phone_channel_is_unavailable(string $unavailable): void
    {
        if ($unavailable === 'gateway') {
            config(['services.sms.provider' => null]);
        } else {
            $features = Salon::current()->features;
            $features[$unavailable] = false;
            Salon::current()->update(['features' => $features]);
        }
        $service = Service::factory()->create();
        $response = $this->get(route('waitlist.join'))->assertOk();
        $this->assertTrue($this->input($response->getContent(), 'email')->hasAttribute('required'));
        $this->post(route('waitlist.store'), $this->waitlistData($service) + ['phone' => '+36301234567'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('waitlist_entries', 0);
    }

    public function test_waitlist_still_accepts_email_without_phone_and_rejects_missing_contact(): void
    {
        $service = Service::factory()->create();
        $this->post(route('waitlist.store'), $this->waitlistData($service))->assertSessionHasErrors('email');
        $this->post(route('waitlist.store'), $this->waitlistData($service) + ['email' => 'guest@example.test'])->assertSessionHasNoErrors();
        $this->assertSame('guest@example.test', WaitlistEntry::sole()->guest->email);
    }

    public function test_management_defaults_to_the_booked_staff_and_can_filter_another_eligible_colleague(): void
    {
        [$booking, $other] = $this->bookingWithColleagues();
        $this->get(route('booking.manage', $booking->manage_token))->assertOk()
            ->assertViewHas('staffId', $booking->staff_id)
            ->assertViewHas('slots', fn (array $slots): bool => count($slots) > 0 && collect($slots)->every(fn (array $slot): bool => $slot['staff_id'] === $booking->staff_id));
        $this->get(route('booking.manage', ['token' => $booking->manage_token, 'staff_id' => $other->id]))->assertOk()
            ->assertViewHas('staffId', $other->id)
            ->assertViewHas('slots', fn (array $slots): bool => count($slots) > 0 && collect($slots)->every(fn (array $slot): bool => $slot['staff_id'] === $other->id));
        $this->get(route('booking.manage', ['token' => $booking->manage_token, 'staff_id' => '']))->assertOk()
            ->assertViewHas('staffId', null)
            ->assertViewHas('slots', fn (array $slots): bool => collect($slots)->pluck('staff_id')->unique()->count() === 2);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_management_next_day_keeps_staff_token_and_ignores_the_original_booking(): void
    {
        [$booking] = $this->bookingWithColleagues();
        Salon::current()->update(['opening_hours' => [3 => [['10:00', '11:00']]], 'horizon_days' => 3]);
        $booking->staff->update(['weekly_hours' => [3 => [['10:00', '11:00']]]]);
        $booking->update(['starts_at' => CarbonImmutable::parse('2026-10-21T08:00:00Z'), 'ends_at' => CarbonImmutable::parse('2026-10-21T09:00:00Z'), 'busy_from' => CarbonImmutable::parse('2026-10-21T08:00:00Z'), 'busy_until' => CarbonImmutable::parse('2026-10-21T09:00:00Z')]);
        $response = $this->get(route('booking.manage', ['token' => $booking->manage_token, 'date' => '2026-10-20']))->assertOk()
            ->assertViewHas('slots', [])
            ->assertViewHas('nextAvailable', fn (?array $next): bool => ($next['date'] ?? null) === '2026-10-21');
        $response->assertSee(route('booking.manage', ['token' => $booking->manage_token, 'date' => '2026-10-21', 'staff_id' => $booking->staff_id]));
        $this->get(route('booking.manage', ['token' => $booking->manage_token, 'date' => '2026-10-22']))->assertOk()->assertViewHas('nextAvailable', null);
        $this->assertSame(1, $booking->fresh()->revision);
    }

    public function test_management_rejects_invalid_date_and_ineligible_or_inactive_staff(): void
    {
        [$booking, $other] = $this->bookingWithColleagues();
        $unrelated = Staff::factory()->create();
        $other->update(['active' => false]);
        foreach ([$unrelated->id, $other->id, -1, 'abc'] as $staffId) {
            $this->getJson(route('booking.manage', ['token' => $booking->manage_token, 'staff_id' => $staffId]))->assertUnprocessable()->assertJsonValidationErrors('staff_id');
        }
        $this->getJson(route('booking.manage', ['token' => $booking->manage_token, 'date' => '2026-02-30']))->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->get(route('booking.manage', str_repeat('a', 64)))->assertNotFound();
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_next_day_search_respects_the_selected_staff_and_booking_notice(): void
    {
        [$booking, $other] = $this->bookingWithColleagues();
        Salon::current()->update(['horizon_days' => 3]);
        $booking->staff->update(['weekly_hours' => []]);
        $other->update(['weekly_hours' => [3 => [['12:00', '13:00']]]]);
        $this->get(route('booking.manage', ['token' => $booking->manage_token, 'date' => '2026-10-20']))->assertOk()->assertViewHas('nextAvailable', null);
        $this->get(route('booking.manage', ['token' => $booking->manage_token, 'date' => '2026-10-20', 'staff_id' => $other->id]))->assertOk()
            ->assertViewHas('nextAvailable', fn (?array $next): bool => ($next['date'] ?? null) === '2026-10-21' && $next['slots'][0]['staff_id'] === $other->id);
        Salon::current()->update(['min_notice_minutes' => 4320]);
        $this->get(route('booking.manage', ['token' => $booking->manage_token, 'date' => '2026-10-20', 'staff_id' => $other->id]))->assertOk()->assertViewHas('nextAvailable', null);
    }

    public function test_changing_staff_updates_the_existing_booking_and_stale_revision_cannot_overwrite_it(): void
    {
        [$booking, $other] = $this->bookingWithColleagues();
        $originalToken = $booking->manage_token;
        $originalGuest = $booking->guest_id;
        $data = ['action' => 'move', 'revision' => $booking->revision, 'slot' => $other->id.'|2026-10-21T12:00:00+02:00'];
        $this->from(route('booking.manage', $originalToken))->post(route('booking.update', $originalToken), $data)->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame($other->id, $booking->staff_id);
        $this->assertSame($originalGuest, $booking->guest_id);
        $this->assertSame($originalToken, $booking->manage_token);
        $this->assertSame(2, $booking->revision);
        $this->assertSame('2026-10-21 10:00', $booking->starts_at->utc()->format('Y-m-d H:i'));
        $this->post(route('booking.update', $originalToken), array_replace($data, ['slot' => $other->id.'|2026-10-22T12:00:00+02:00']))->assertSessionHasErrors();
        $this->assertSame('2026-10-21 10:00', $booking->fresh()->starts_at->utc()->format('Y-m-d H:i'));
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_success_exposes_a_copy_action_for_the_private_link_and_demo_owner_navigation_requires_auth(): void
    {
        [$booking] = $this->bookingWithColleagues();
        Salon::current()->update(['demo' => true]);
        $this->withSession(['last_booking' => $booking->id])->get(route('booking.success'))->assertOk()
            ->assertSee('data-copy-booking-link', false)->assertSee('role="status"', false)
            ->assertSee(route('booking.manage', $booking->manage_token))
            ->assertSee('Nézd meg a tulajdonosi oldalt')->assertSee(route('admin.today'));
        $this->get(route('admin.today'))->assertRedirect(route('login'));
    }

    /** @return array{Booking, Staff} */
    private function bookingWithColleagues(): array
    {
        $service = Service::factory()->create(['buffer_after' => 0]);
        $other = Staff::factory()->create(['name' => 'Másik szakember']);
        $service->staff()->attach($other);
        $booking = Booking::factory()->create(['service_id' => $service->id, 'staff_id' => $service->staff()->first()->id, 'buffer_after' => 0]);

        return [$booking->refresh(), $other];
    }

    /** @return array{service_id: int, preferred_date: string, preferred_until: string, name: string, notify_consent: int} */
    private function waitlistData(Service $service): array
    {
        return ['service_id' => $service->id, 'preferred_date' => '2026-10-20', 'preferred_until' => '2026-10-22', 'name' => 'Várakozó vendég', 'notify_consent' => 1];
    }

    private function input(string $html, string $name): \DOMElement
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $input = (new \DOMXPath($document))->query('//input[@name="'.$name.'"]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $input);

        return $input;
    }
}
