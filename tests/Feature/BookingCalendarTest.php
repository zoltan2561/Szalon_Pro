<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\User;
use App\Services\BookingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19T06:00:00Z'));
        Salon::factory()->create();
    }

    public function test_guest_download_has_utc_times_and_no_private_contact_or_management_link(): void
    {
        $booking = Booking::factory()->create(['starts_at' => '2026-10-25 01:30:00', 'ends_at' => '2026-10-25 02:30:00']);
        $this->get(route('booking.calendar', $booking->manage_token))->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="szalonpro-idopont.ics"')
            ->assertSee('DTSTART:20261025T013000Z')->assertSee('DTEND:20261025T023000Z')
            ->assertDontSee($booking->manage_token)->assertDontSee($booking->guest->email);
    }

    public function test_unknown_and_expired_tokens_cannot_download(): void
    {
        $booking = Booking::factory()->create(['token_expires_at' => now()->subMinute()]);
        $this->get(route('booking.calendar', $booking->manage_token))->assertNotFound();
        $this->get(route('booking.calendar', str_repeat('a', 64)))->assertNotFound();
    }

    public function test_staff_cannot_export_another_staff_members_appointment(): void
    {
        $booking = Booking::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('admin.booking.calendar', $booking))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_id' => $booking->staff_id]))->get(route('admin.booking.calendar', $booking))->assertOk();
    }

    public function test_updates_keep_the_uid_and_change_the_sequence_and_cancelled_status(): void
    {
        $booking = Booking::factory()->create();
        $first = app(BookingCalendar::class)->content($booking);
        $booking->update(['status' => 'cancelled', 'revision' => 2]);
        $second = app(BookingCalendar::class)->content($booking);
        preg_match('/UID:([^\r]+)/', $first, $uid);
        $this->assertStringContainsString($uid[0], $second);
        $this->assertStringContainsString('SEQUENCE:2', $second);
        $this->assertStringContainsString('STATUS:CANCELLED', $second);
    }

    public function test_ics_escapes_content_and_folds_utf8_without_injecting_a_second_event(): void
    {
        $booking = Booking::factory()->create(['service_name' => str_repeat('Árnyalás, szárítás; ', 12)."\r\nBEGIN:VEVENT"]);
        $content = app(BookingCalendar::class)->content($booking);
        $this->assertSame(1, substr_count($content, "\r\nBEGIN:VEVENT\r\n"));
        $this->assertStringContainsString('\\,', $content);
        $this->assertStringContainsString('\\;', $content);
        foreach (explode("\r\n", $content) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'));
        }
    }

    public function test_guest_manage_and_owner_detail_show_calendar_actions(): void
    {
        $booking = Booking::factory()->create();
        $this->get(route('booking.manage', $booking->manage_token))->assertOk()->assertSee('Időpont mentése a naptáramba');
        $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('admin.booking', $booking))->assertOk()->assertSee('Apple / Outlook naptár');
    }

    public function test_series_export_contains_only_the_same_guests_authorized_visits(): void
    {
        $first = Booking::factory()->create(['series_key' => Str::uuid()->toString()]);
        Booking::factory()->create(['series_key' => $first->series_key, 'guest_id' => $first->guest_id]);
        Booking::factory()->create(['series_key' => $first->series_key]);
        $response = $this->get(route('booking.calendar', ['token' => $first->manage_token, 'series' => 1]))->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'BEGIN:VEVENT'));
        $this->assertSame(1, substr_count($response->getContent(), 'BEGIN:VCALENDAR'));
        $worker = User::factory()->create(['role' => 'staff', 'staff_id' => $first->staff_id]);
        $own = $this->actingAs($worker)->get(route('admin.booking.calendar', ['booking' => $first, 'series' => 1]))->assertOk();
        $this->assertSame(1, substr_count($own->getContent(), 'BEGIN:VEVENT'));
    }
}
