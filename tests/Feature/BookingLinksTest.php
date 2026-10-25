<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingLinks;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_links_and_svg_qr_respect_module_and_role(): void
    {
        $salon = Salon::factory()->create(['features' => ['sharing' => true]]);
        $service = Service::factory()->create();
        $staff = $service->staff()->first();
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get(route('admin.sharing.index', ['service_id' => $service->id, 'staff_id' => $staff->id]))->assertOk()->assertSee('utm_source=google')->assertSee('service_id='.$service->id);
        $this->get(route('admin.sharing.qr', ['utm_source' => 'qr']))->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('<svg', false);
        $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id]))->get(route('admin.sharing.index'))->assertForbidden();
        $salon->update(['features' => []]);
        $this->actingAs($owner)->get(route('admin.sharing.index'))->assertNotFound();
    }

    public function test_booking_keeps_channel_separate_from_online_manual_source(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        Salon::factory()->create(['features' => ['sharing' => true]]);
        $service = Service::factory()->create();
        $staff = $service->staff()->first();
        $this->get(app(BookingLinks::class)->url('instagram', $service->id, $staff->id))->assertOk()->assertSee('name="referral_source" value="instagram"', false);
        $this->post(route('booking.store'), ['service_id' => $service->id, 'slot' => $staff->id.'|2026-10-19T10:00:00+02:00', 'name' => 'Minta', 'email' => 'minta@example.test', 'terms' => 1, 'referral_source' => 'instagram', 'request_key' => Str::uuid()->toString()])->assertRedirect();
        $booking = Booking::firstOrFail();
        $this->assertSame('online', $booking->source);
        $this->assertSame('instagram', $booking->referral_source);
    }
}
