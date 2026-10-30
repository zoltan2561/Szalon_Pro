<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\NewBookingArrived;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingInboxTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;

    private User $owner;

    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        Salon::factory()->create();
        $this->service = Service::factory()->create();
        $this->owner = User::factory()->create(['role' => 'owner']);
        $this->worker = User::factory()->create(['role' => 'staff', 'staff_id' => $this->service->staff()->firstOrFail()->id]);
    }

    /** @return array<string, mixed> */
    private function data(string $time = '10:00', string $name = 'Új vendég'): array
    {
        return ['service_id' => $this->service->id, 'staff_id' => $this->worker->staff_id, 'starts_at' => '2026-10-19T'.$time.':00+02:00', 'name' => $name, 'email' => 'guest@example.test', 'request_key' => Str::uuid()->toString()];
    }

    public function test_online_booking_notifies_only_owners_and_assigned_worker_once_without_storing_personal_data(): void
    {
        $other = User::factory()->create(['role' => 'staff']);
        $demo = User::factory()->create(['role' => 'owner']);
        $demo->forceFill(['is_demo_viewer' => true])->save();
        $data = $this->data();
        $booking = app(BookingService::class)->create($data);
        $this->assertSame($booking->id, app(BookingService::class)->create($data)->id);
        foreach ([$this->owner, $this->worker] as $recipient) {
            $this->assertSame(1, $recipient->unreadNotifications()->count());
            $this->assertSame(['booking_id' => $booking->id], $recipient->unreadNotifications()->first()->data);
        }
        $this->assertSame(0, $other->notifications()->count());
        $this->assertSame(0, $demo->notifications()->count());
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_manual_bookings_and_rolled_back_transactions_do_not_create_alerts(): void
    {
        app(BookingService::class)->create($this->data(), $this->owner);
        $this->assertDatabaseCount('notifications', 0);
        try {
            DB::transaction(function (): void {
                app(BookingService::class)->create($this->data('14:00'));
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_inbox_escapes_names_and_does_not_expose_guest_management_tokens(): void
    {
        $booking = app(BookingService::class)->create($this->data('10:00', '<script>private-input</script>'));
        $this->actingAs($this->owner)->get(route('admin.today'))->assertOk()
            ->assertSee('data-booking-inbox', false)->assertSee('&lt;script&gt;private-input&lt;/script&gt;', false)
            ->assertDontSee('<script>private-input</script>', false)->assertDontSee($booking->manage_token);
        $this->getJson(route('admin.inbox.index'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('count', 1)->assertJsonPath('through_id', $booking->id);
        $this->assertSame(1, $this->owner->unreadNotifications()->count());
    }

    public function test_opening_an_alert_marks_only_the_current_users_alert_read_and_checks_ownership(): void
    {
        $booking = app(BookingService::class)->create($this->data());
        $mine = $this->owner->unreadNotifications()->first();
        $others = $this->worker->unreadNotifications()->first();
        $this->actingAs($this->owner)->post(route('admin.inbox.open', $others->id))->assertNotFound();
        $this->post(route('admin.inbox.open', $mine->id))->assertRedirect(route('admin.booking', $booking));
        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull($others->fresh()->read_at);
    }

    public function test_mark_all_keeps_later_arrivals_and_other_users_unread(): void
    {
        $first = app(BookingService::class)->create($this->data());
        $this->actingAs($this->owner)->getJson(route('admin.inbox.index'))->assertJsonPath('through_id', $first->id);
        $next = app(BookingService::class)->create($this->data('14:00'));
        $this->post(route('admin.inbox.read'), ['through_id' => $first->id])->assertRedirect();
        $this->assertSame([$next->id], $this->owner->unreadNotifications()->get()->pluck('data.booking_id')->all());
        $this->assertSame(2, $this->worker->unreadNotifications()->count());
    }

    public function test_staff_cannot_see_or_open_a_booking_that_was_reassigned(): void
    {
        $booking = app(BookingService::class)->create($this->data());
        $notification = $this->worker->unreadNotifications()->first();
        $booking->update(['staff_id' => Staff::factory()->create()->id]);
        $this->actingAs($this->worker)->getJson(route('admin.inbox.index'))->assertOk()->assertJsonPath('count', 0)->assertDontSee('Új vendég');
        $this->post(route('admin.inbox.open', $notification->id))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_public_demo_cannot_read_the_private_inbox_or_change_read_states(): void
    {
        Salon::current()->update(['demo' => true]);
        config(['salon.demo_viewer_enabled' => true, 'salon.demo_viewer_email' => 'demo@example.test']);
        $demo = User::factory()->create(['email' => 'demo@example.test', 'role' => 'owner']);
        $demo->forceFill(['is_demo_viewer' => true])->save();
        app(BookingService::class)->create($this->data());
        $notification = $this->owner->unreadNotifications()->first();
        $this->actingAs($demo)->getJson(route('admin.inbox.index'))->assertForbidden();
        $this->post(route('admin.inbox.open', $notification->id))->assertForbidden();
        $this->post(route('admin.inbox.read'), ['through_id' => 1000])->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_background_poll_does_not_replace_the_page_used_by_back_redirects(): void
    {
        $page = route('admin.calendar', ['date' => '2026-10-19']);
        $this->actingAs($this->owner)->get($page)->assertOk();
        $this->getJson(route('admin.inbox.index'))->assertOk();
        $this->post(route('admin.inbox.read'), ['through_id' => 0])->assertRedirect($page);
    }

    public function test_an_older_unread_alert_is_not_hidden_by_eight_newer_read_alerts(): void
    {
        $old = Booking::factory()->create(['guest_id' => Guest::factory()->create(['name' => 'Older unread guest'])->id]);
        $this->owner->notify(new NewBookingArrived($old->id));
        $this->travel(1)->minutes();
        foreach (Booking::factory()->count(8)->create() as $booking) {
            $this->owner->notify(new NewBookingArrived($booking->id));
            $this->owner->notifications()->where('data->booking_id', $booking->id)->update(['read_at' => now()]);
        }
        $this->actingAs($this->owner)->getJson(route('admin.inbox.index'))->assertOk()->assertJsonPath('count', 1)->assertSee('Older unread guest');
    }
}
