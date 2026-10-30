<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WaitlistOffer;
use App\Services\BookingService;
use App\Services\WaitlistOffers;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class WaitlistOffersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19T06:00:00Z'));
        Salon::factory()->create(['features' => ['waitlist' => true, 'waitlist_auto' => true]]);
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'mail.salon.example', 'mail.from.address' => 'booking@salon.example']);
    }

    public function test_only_consented_waiters_receive_a_matching_offer_and_cooldown_prevents_repetition(): void
    {
        $entry = $this->entry();
        WaitlistEntry::factory()->create(['service_id' => $entry->service_id]);
        $this->assertSame(1, app(WaitlistOffers::class)->prepare());
        $offer = WaitlistOffer::sole();
        $this->assertSame($entry->id, $offer->waitlist_entry_id);
        $this->assertSame('12:00', $offer->starts_at->setTimezone('Europe/Budapest')->format('H:i'));
        $this->assertSame(0, app(WaitlistOffers::class)->prepare());
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('waitlist_offers', 1);
    }

    public function test_guest_explicitly_accepts_the_offer_and_duplicate_submission_creates_no_duplicate_booking(): void
    {
        $entry = $this->entry();
        app(WaitlistOffers::class)->prepare();
        $offer = WaitlistOffer::sole();
        $this->get(route('waitlist.offer', $offer->manage_token))->assertOk()->assertSee('Ezt az időpontot lefoglalom');
        $this->post(route('waitlist.accept', $offer->manage_token), ['terms' => 1])->assertRedirect(route('booking.success'));
        $this->post(route('waitlist.accept', $offer->manage_token), ['terms' => 1])->assertRedirect(route('booking.success'));
        $this->assertDatabaseCount('bookings', 1);
        $this->assertSame($entry->guest_id, Booking::sole()->guest_id);
        $this->assertSame('booked', $entry->fresh()->status);
        $this->assertSame('accepted', $offer->fresh()->status);
    }

    public function test_taken_or_expired_offers_never_overbook_and_keep_the_waiter_waiting(): void
    {
        $entry = $this->entry();
        app(WaitlistOffers::class)->prepare();
        $offer = WaitlistOffer::sole();
        app(BookingService::class)->create(['service_id' => $entry->service_id, 'staff_id' => $offer->staff_id, 'starts_at' => $offer->starts_at->format('Y-m-d\TH:i:sP'), 'name' => 'Másik vendég', 'email' => 'other@example.test', 'request_key' => Str::uuid()->toString()]);
        $this->post(route('waitlist.accept', $offer->manage_token), ['terms' => 1])->assertSessionHasErrors('offer');
        $this->assertDatabaseCount('bookings', 1);
        $this->assertSame('waiting', $entry->fresh()->status);
        $offer->update(['expires_at' => now()->subMinute()]);
        $this->get(route('waitlist.offer', $offer->manage_token))->assertOk()->assertSee('Ez az ajánlat már nem foglalható');
    }

    public function test_public_waitlist_signup_is_not_a_booking_and_can_be_withdrawn(): void
    {
        $service = Service::factory()->create();
        $this->post(route('waitlist.store'), ['service_id' => $service->id, 'preferred_date' => '2026-10-20', 'preferred_until' => '2026-10-22', 'name' => 'Várakozó vendég', 'email' => 'wait@example.test', 'notify_consent' => 1])->assertSessionHasNoErrors();
        $entry = WaitlistEntry::sole();
        $this->assertDatabaseCount('bookings', 0);
        $this->get(route('waitlist.request', $entry->manage_token))->assertOk()->assertSee('A várólistán vagy');
        $this->post(route('waitlist.cancel', $entry->manage_token))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $entry->fresh()->status);
        $this->assertSame(0, app(WaitlistOffers::class)->prepare());
    }

    public function test_disabled_or_unconfigured_automatic_mode_prepares_no_offer(): void
    {
        $this->entry();
        config(['mail.default' => 'array']);
        $this->assertSame(0, app(WaitlistOffers::class)->prepare());
        $this->assertDatabaseCount('waitlist_offers', 0);
    }

    public function test_pending_offer_is_sent_once_through_the_existing_mail_transport(): void
    {
        $this->entry();
        app(WaitlistOffers::class)->prepare();
        Mail::shouldReceive('send')->once()->andReturnNull();
        $this->assertSame(1, app(WaitlistOffers::class)->sendDue());
        $this->assertSame(0, app(WaitlistOffers::class)->sendDue());
        $this->assertSame('sent', WaitlistOffer::sole()->status);
    }

    public function test_owner_cannot_enable_automatic_offers_without_a_working_channel(): void
    {
        config(['mail.default' => 'array']);
        $this->actingAs(User::factory()->create(['role' => 'owner']))->post(route('admin.modules.update'), ['features' => ['waitlist' => 1, 'waitlist_auto' => 1]])->assertSessionHasErrors('features.waitlist_auto');
    }

    private function entry(): WaitlistEntry
    {
        $service = Service::factory()->create(['buffer_before' => 0, 'buffer_after' => 0]);

        return WaitlistEntry::factory()->create(['service_id' => $service->id, 'preferred_date' => '2026-10-20', 'preferred_until' => '2026-10-22', 'time_from' => '12:00', 'time_until' => '15:00', 'notification_consent_at' => now()]);
    }
}
