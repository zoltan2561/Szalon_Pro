<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TimeBlock;
use App\Models\User;
use App\Services\OperationsHealth;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkspaceExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19T06:00:00Z'));
    }

    public function test_guest_profile_puts_nearest_visits_first_without_leaking_other_staff_bookings_or_notes(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = Guest::factory()->create(['notes' => 'Csak engedéllyel látható szakmai megjegyzés']);
        $staff = Staff::factory()->create();
        $far = Booking::factory()->create(['guest_id' => $guest->id, 'staff_id' => $staff->id, 'starts_at' => '2026-11-19 09:00:00', 'ends_at' => '2026-11-19 10:00:00']);
        $near = Booking::factory()->create(['guest_id' => $guest->id, 'staff_id' => $staff->id, 'starts_at' => '2026-10-20 09:00:00', 'ends_at' => '2026-10-20 10:00:00']);
        $past = Booking::factory()->create(['guest_id' => $guest->id, 'staff_id' => $staff->id, 'status' => 'completed', 'starts_at' => '2026-10-18 09:00:00', 'ends_at' => '2026-10-18 10:00:00']);

        $this->actingAs($owner)->get(route('admin.guest', $guest))->assertOk()
            ->assertViewHas('upcomingBookings', fn ($bookings) => $bookings->modelKeys() === [$near->id, $far->id])
            ->assertViewHas('pastBookings', fn ($bookings) => $bookings->modelKeys() === [$past->id])
            ->assertSeeInOrder(['Szakmai megjegyzés', 'Következő alkalmak', '2026. 10. 20.', '2026. 11. 19.', 'Látogatási előzmények']);

        $other = Booking::factory()->create(['guest_id' => $guest->id, 'service_name' => 'Másik munkatárs szolgáltatása']);
        $staffUser = User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id, 'can_view_notes' => false]);
        $this->actingAs($staffUser)->get(route('admin.guest', $guest))->assertOk()
            ->assertDontSee('Csak engedéllyel látható szakmai megjegyzés')
            ->assertDontSee('href="'.route('admin.booking', $other).'"', false);
    }

    public function test_booking_list_defaults_to_nearest_upcoming_and_history_is_separate(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $far = Booking::factory()->create(['starts_at' => '2026-11-19 09:00:00', 'ends_at' => '2026-11-19 10:00:00']);
        $near = Booking::factory()->create(['starts_at' => '2026-10-20 09:00:00', 'ends_at' => '2026-10-20 10:00:00']);
        $past = Booking::factory()->create(['status' => 'completed', 'starts_at' => '2026-10-18 09:00:00', 'ends_at' => '2026-10-18 10:00:00']);
        $cancelled = Booking::factory()->create(['status' => 'cancelled']);

        $this->actingAs($owner)->get(route('admin.bookings.index'))->assertOk()
            ->assertViewHas('scope', 'upcoming')
            ->assertViewHas('bookings', fn ($bookings) => $bookings->modelKeys() === [$near->id, $far->id]);
        $this->get(route('admin.bookings.index', ['scope' => 'history']))->assertOk()
            ->assertViewHas('bookings', fn ($bookings) => $bookings->total() === 2 && $bookings->contains('id', $past->id) && $bookings->contains('id', $cancelled->id));
        $this->get(route('admin.bookings.index', ['scope' => 'all']))->assertOk()
            ->assertViewHas('bookings', fn ($bookings) => $bookings->total() === 4);
        $this->get(route('admin.bookings.index', ['scope' => 'invalid']))->assertSessionHasErrors('scope');
    }

    public function test_full_public_day_offers_next_available_day_for_the_selected_staff(): void
    {
        Salon::factory()->create(['min_notice_minutes' => 0, 'horizon_days' => 7]);
        $service = Service::factory()->create();
        $staff = $service->staff()->firstOrFail();
        $service->staff()->attach(Staff::factory()->create());
        TimeBlock::factory()->create(['staff_id' => $staff->id, 'starts_at' => '2026-10-18 22:00:00', 'ends_at' => '2026-10-19 22:00:00']);

        $this->get(route('booking.form', ['date' => '2026-10-19', 'service_id' => $service->id, 'staff_id' => $staff->id]))->assertOk()
            ->assertViewHas('slots', [])
            ->assertViewHas('nextAvailable', fn ($next) => $next['date'] === '2026-10-20' && collect($next['slots'])->every(fn (array $slot): bool => $slot['staff_id'] === $staff->id))
            ->assertSee('Következő szabad:')->assertSee('data-next-available', false);
    }

    public function test_public_recovery_never_offers_a_day_beyond_the_booking_horizon(): void
    {
        Salon::factory()->create(['min_notice_minutes' => 0, 'horizon_days' => 1]);
        $service = Service::factory()->create();
        TimeBlock::factory()->create(['staff_id' => $service->staff()->firstOrFail()->id, 'starts_at' => '2026-10-18 22:00:00', 'ends_at' => '2026-10-22 22:00:00']);

        $this->get(route('booking.form', ['date' => '2026-10-19', 'service_id' => $service->id]))->assertOk()
            ->assertViewHas('nextAvailable', null)->assertViewHas('nextAvailableUrl', null)
            ->assertDontSee('data-next-available', false);
    }

    public function test_rebooking_recovery_preserves_the_existing_guest_management_link(): void
    {
        Salon::factory()->create(['min_notice_minutes' => 0]);
        $service = Service::factory()->create();
        $staff = $service->staff()->firstOrFail();
        $token = str_repeat('a', 64);
        Booking::factory()->create(['service_id' => $service->id, 'staff_id' => $staff->id, 'token_hash' => hash('sha256', $token), 'token_expires_at' => now()->addMonth()]);
        TimeBlock::factory()->create(['staff_id' => $staff->id, 'starts_at' => '2026-10-18 22:00:00', 'ends_at' => '2026-10-19 22:00:00']);

        $this->get(route('booking.rebook.form', ['token' => $token, 'date' => '2026-10-19']))->assertOk()
            ->assertViewHas('nextAvailableUrl', fn ($url) => str_starts_with($url, route('booking.rebook.form', $token)));
    }

    public function test_manual_booking_selects_existing_guest_before_slots_and_uses_a_complete_review(): void
    {
        Salon::factory()->create();
        $service = Service::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get(route('admin.create'))->assertOk()
            ->assertSeeInOrder(['id="manual-booking-guest"', 'class="slots-field"', 'data-booking-review', 'Foglalás mentése'], false)
            ->assertSee($service->staff()->firstOrFail()->name)->assertDontSee('Elérhető szakember');
        $this->get(route('admin.today'))->assertOk()
            ->assertSee('href="'.route('admin.create', ['nearest' => 1]).'"', false)
            ->assertDontSee('data-quick-sheet', false);
    }

    public function test_mobile_week_preserves_staff_scope_and_selected_day(): void
    {
        Salon::factory()->create();
        $staff = Staff::factory()->create();
        $user = User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id]);
        $own = Booking::factory()->create(['staff_id' => $staff->id, 'starts_at' => '2026-10-20 09:00:00', 'ends_at' => '2026-10-20 10:00:00']);
        $other = Booking::factory()->create(['starts_at' => '2026-10-20 09:00:00', 'ends_at' => '2026-10-20 10:00:00']);
        $this->actingAs($user)->get(route('admin.calendar', ['date' => '2026-10-20', 'view' => 'week']))->assertOk()
            ->assertSee('data-week-day="2026-10-20"', false)
            ->assertSee('href="'.route('admin.booking', $own).'"', false)
            ->assertDontSee('href="'.route('admin.booking', $other).'"', false);
    }

    public function test_demo_hides_expected_mail_configuration_not_real_operational_failures(): void
    {
        Salon::factory()->create(['demo' => true]);
        $this->partialMock(OperationsHealth::class, function ($mock): void {
            $mock->shouldReceive('warnings')->andReturn(['mail' => 'DEMO_MAIL_WARNING', 'password_reset' => 'DEMO_RESET_WARNING', 'billing_documents' => 'Valódi működési teendő']);
        });
        $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('admin.services'))->assertOk()
            ->assertDontSee('DEMO_MAIL_WARNING')->assertDontSee('DEMO_RESET_WARNING')->assertSee('Valódi működési teendő');
    }

    public function test_future_visit_cannot_be_closed_from_desktop_or_mobile_actions(): void
    {
        Salon::factory()->create();
        $booking = Booking::factory()->create();
        $response = $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('admin.booking', $booking))->assertOk()
            ->assertSee('A látogatás a kezdésétől zárható le.');
        preg_match_all('/<button\b[^>]*name="action"[^>]*value="(?:complete|no_show)"[^>]*>/', $response->getContent(), $buttons);
        $this->assertCount(3, $buttons[0]);
        foreach ($buttons[0] as $button) {
            $this->assertStringContainsString('disabled', $button);
        }
    }
}
