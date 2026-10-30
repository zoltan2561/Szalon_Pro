<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMobileWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_separates_active_visits_from_future_visits_and_preserves_staff_scope(): void
    {
        $this->travelTo(now('Europe/Budapest')->setDate(2026, 10, 30)->setTime(12, 0));
        Salon::factory()->create();
        $active = Booking::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'started_at' => now()->subMinutes(50)]);
        $arrived = Booking::factory()->create(['staff_id' => $active->staff_id, 'starts_at' => now()->addMinutes(30), 'ends_at' => now()->addMinutes(90), 'arrived_at' => now()]);
        $future = Booking::factory()->create(['staff_id' => $active->staff_id, 'starts_at' => now()->addHours(2), 'ends_at' => now()->addHours(3)]);
        $cancelled = Booking::factory()->create(['staff_id' => $active->staff_id, 'status' => 'cancelled', 'starts_at' => now(), 'started_at' => now()]);
        $other = Booking::factory()->create(['starts_at' => now()->addMinutes(10)]);
        $staff = User::factory()->create(['role' => 'staff', 'staff_id' => $active->staff_id]);
        $this->actingAs($staff)->get(route('admin.today'))->assertOk()
            ->assertViewHas('activeBookings', fn ($items) => $items?->modelKeys() === [$active->id, $arrived->id])
            ->assertViewHas('next', fn ($item) => $item->id === $future->id)
            ->assertDontSee('href="'.route('admin.booking', $other).'"', false);
    }

    public function test_owner_calendar_filter_preserves_navigation_and_staff_cannot_expand_scope(): void
    {
        Salon::factory()->create();
        $own = Booking::factory()->create(['starts_at' => '2026-10-30 10:00:00', 'ends_at' => '2026-10-30 11:00:00']);
        $other = Booking::factory()->create(['starts_at' => '2026-10-30 10:00:00', 'ends_at' => '2026-10-30 11:00:00']);
        $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('admin.calendar', ['date' => '2026-10-30', 'view' => 'week', 'staff_id' => $own->staff_id]))->assertOk()
            ->assertViewHas('bookings', fn ($items) => $items->modelKeys() === [$own->id])
            ->assertSee(e(route('admin.calendar', ['date' => '2026-11-02', 'view' => 'week', 'staff_id' => $own->staff_id])), false)
            ->assertSee(e(route('admin.create', ['date' => '2026-10-30', 'staff_id' => $own->staff_id])), false);
        $this->getJson(route('admin.calendar', ['staff_id' => 999999]))->assertUnprocessable();
        $staff = User::factory()->create(['role' => 'staff', 'staff_id' => $own->staff_id]);
        $this->actingAs($staff)->get(route('admin.calendar', ['staff_id' => $other->staff_id]))->assertForbidden();
    }

    public function test_read_only_staff_have_no_create_or_rebooking_links(): void
    {
        Salon::factory()->create();
        $booking = Booking::factory()->create();
        $staff = User::factory()->create(['role' => 'staff', 'staff_id' => $booking->staff_id, 'can_manage_bookings' => false]);
        foreach (['admin.today', 'admin.calendar'] as $route) {
            $this->actingAs($staff)->get(route($route))->assertOk()->assertDontSee(route('admin.create'), false);
        }
        $this->get(route('admin.booking', $booking))->assertOk()->assertDontSee(route('admin.create'), false);
        $this->get(route('admin.guest', $booking->guest))->assertOk()->assertDontSee(route('admin.create'), false);
    }

    public function test_previous_completed_work_notes_are_scoped_and_never_prefill_current_notes(): void
    {
        Salon::factory()->create();
        $booking = Booking::factory()->create();
        $previous = Booking::factory()->create(['guest_id' => $booking->guest_id, 'staff_id' => $booking->staff_id, 'status' => 'completed', 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHour(), 'work_notes' => ['formula' => 'Korábbi látható recept']]);
        Booking::factory()->create(['guest_id' => $booking->guest_id, 'status' => 'completed', 'starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHour(), 'work_notes' => ['formula' => 'Más munkatárs titkos receptje']]);
        $staff = User::factory()->create(['role' => 'staff', 'staff_id' => $booking->staff_id, 'can_view_notes' => true]);
        $this->actingAs($staff)->get(route('admin.booking', $booking))->assertOk()
            ->assertViewHas('previousWorkNotes', fn ($item) => $item?->id === $previous->id)
            ->assertSee('Korábbi látható recept')->assertDontSee('Más munkatárs titkos receptje')
            ->assertSee('name="work_notes[formula]" maxlength="1500" value=""', false);
        $this->assertNull($booking->fresh()->work_notes);
        $staff->update(['can_view_notes' => false]);
        $this->get(route('admin.booking', $booking))->assertOk()->assertDontSee('Korábbi látható recept')->assertDontSee('Előző munkalap');
    }

    public function test_mobile_main_action_advances_arrival_and_start_using_the_existing_booking_update(): void
    {
        $this->travelTo(now('Europe/Budapest')->setDate(2026, 10, 30)->setTime(12, 0));
        Salon::factory()->create();
        $booking = Booking::factory()->create(['starts_at' => now()->subMinutes(10), 'ends_at' => now()->addMinutes(50)]);
        $booking->refresh();
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        foreach (['arrive', 'start'] as $action) {
            $response = $this->get(route('admin.booking', $booking))->assertOk();
            preg_match('/<nav class="booking-mobile-actions".*?<\/nav>/s', $response->getContent(), $mobileActions);
            $this->assertStringContainsString('form="booking-stage-action" name="action" value="'.$action.'"', $mobileActions[0]);
            $this->post(route('admin.booking.update', $booking), ['revision' => $booking->revision, 'action' => $action])->assertSessionHasNoErrors()->assertRedirect();
            $booking->refresh();
        }
        $this->assertNotNull($booking->arrived_at);
        $this->assertNotNull($booking->started_at);
        $this->get(route('admin.booking', $booking))->assertOk()->assertSee('href="#booking-complete-form"', false);
    }

    public function test_calendar_filters_submit_to_the_current_today_or_calendar_route(): void
    {
        Salon::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        foreach (['admin.today', 'admin.calendar'] as $routeName) {
            $this->get(route($routeName))->assertOk()->assertSee('data-auto-filter="calendar" method="get" action="'.route($routeName).'"', false);
        }
    }

    public function test_today_staff_filter_refresh_context_contains_only_selected_staff_cards_and_actions(): void
    {
        $this->travelTo(now('Europe/Budapest')->setDate(2026, 10, 30)->setTime(12, 0));
        Salon::factory()->create();
        $active = Booking::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'started_at' => now()->subMinutes(50)]);
        $future = Booking::factory()->create(['staff_id' => $active->staff_id, 'starts_at' => now()->addHours(2), 'ends_at' => now()->addHours(3)]);
        $otherActive = Booking::factory()->create(['starts_at' => now()->subMinutes(30), 'ends_at' => now()->addHour(), 'arrived_at' => now()->subMinutes(30)]);
        $otherFuture = Booking::factory()->create(['staff_id' => $otherActive->staff_id, 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
        $response = $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('admin.today', ['date' => '2026-10-30', 'view' => 'week', 'staff_id' => $active->staff_id]))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $contexts = $xpath->query('//*[@data-calendar-day-context]');
        $this->assertCount(1, $contexts);
        $context = $document->saveHTML($contexts->item(0));
        $this->assertStringContainsString('href="'.route('admin.booking', $active).'"', $context);
        $this->assertStringContainsString('href="'.route('admin.booking', $future).'"', $context);
        $this->assertStringNotContainsString('href="'.route('admin.booking', $otherActive).'"', $context);
        $this->assertStringNotContainsString('href="'.route('admin.booking', $otherFuture).'"', $context);
        $this->assertStringContainsString(e(route('admin.create', ['nearest' => 1, 'staff_id' => $active->staff_id])), $context);
        $this->assertCount(2, $xpath->query('//*[@data-calendar-day-context]//select[@name="staff_id"]/option[@value="'.$active->staff_id.'"]'));
        $this->assertCount(0, $xpath->query('//*[@data-calendar-day-context]//select[@name="staff_id"]/option[@value="'.$otherActive->staff_id.'"]'));
    }

    public function test_empty_calendar_and_read_only_today_keep_a_replaceable_context_without_write_actions(): void
    {
        Salon::factory()->create();
        $booking = Booking::factory()->create(['status' => 'cancelled']);
        $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_id' => $booking->staff_id, 'can_manage_bookings' => false]));
        foreach (['admin.today', 'admin.calendar'] as $routeName) {
            $response = $this->get(route($routeName))->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $contexts = $xpath->query('//*[@data-calendar-day-context]');
            $this->assertCount(1, $contexts);
            $context = $document->saveHTML($contexts->item(0));
            $this->assertStringNotContainsString(route('admin.create'), $context);
            $this->assertStringNotContainsString('<form', $context);
        }
    }
}
