<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingAbuseProtectionTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        Salon::factory()->create();
        $this->service = Service::factory()->create();
    }

    private function bookingData(string $time = '2026-10-19T10:00:00+02:00'): array
    {
        return [
            'service_id' => $this->service->id,
            'slot' => $this->service->staff()->first()->id.'|'.$time,
            'name' => 'Teszt Vendég',
            'email' => 'guest@example.test',
            'terms' => 1,
            'request_key' => Str::uuid()->toString(),
        ];
    }

    public function test_booking_form_is_private_and_submissions_remain_idempotent_without_javascript(): void
    {
        $this->get(route('booking.form'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('name="website"', false)->assertSee('hidden aria-hidden="true"', false);
        $data = $this->bookingData();
        $this->post(route('booking.store'), $data)->assertRedirect(route('booking.success'));
        $this->post(route('booking.store'), $data)->assertRedirect(route('booking.success'));
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_honeypot_rejects_automated_submissions_and_rebooking_without_creating_records(): void
    {
        $this->post(route('booking.store'), $this->bookingData() + ['website' => 'spam'])->assertStatus(422);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('guests', 0);
        $this->assertDatabaseCount('outbox_messages', 0);

        $this->post(route('booking.store'), $this->bookingData())->assertRedirect(route('booking.success'));
        $source = Booking::firstOrFail();
        $this->get(route('booking.rebook.form', $source->manage_token))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->post(route('booking.rebook.store', $source->manage_token), $this->bookingData('2026-10-19T12:00:00+02:00') + ['website' => 'spam'])->assertStatus(422);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('guests', 1);
        $this->post(route('booking.rebook.store', $source->manage_token), $this->bookingData('2026-10-19T12:00:00+02:00'))->assertRedirect(route('booking.success'));
        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseCount('guests', 1);
    }

    public function test_submission_limit_is_shared_between_booking_and_rebooking_but_not_other_clients(): void
    {
        $this->post(route('booking.store'), $this->bookingData())->assertRedirect(route('booking.success'));
        $source = Booking::firstOrFail();
        for ($attempt = 0; $attempt < 7; $attempt++) {
            $this->post(route('booking.rebook.store', $source->manage_token), [])->assertRedirect();
        }
        $this->post(route('booking.store'), [])->assertStatus(429)->assertHeader('Retry-After');
        $this->withServerVariables(['HTTP_X_FORWARDED_FOR' => '192.0.2.50'])->post(route('booking.store'), [])->assertStatus(429);
        $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('admin.create'))->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.24'])->post(route('booking.store'), [])->assertRedirect();
    }

    public function test_hourly_limit_still_applies_to_spaced_submissions(): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            if ($attempt > 0 && $attempt % 8 === 0) {
                $this->travel(61)->seconds();
            }
            $this->post(route('booking.store'), [])->assertRedirect();
        }
        $this->post(route('booking.store'), [])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_two_open_booking_forms_have_distinct_idempotency_keys(): void
    {
        $first = $this->get(route('booking.form'))->assertOk();
        $second = $this->get(route('booking.form'))->assertOk();
        $firstKey = $first->viewData('requestKey');
        $secondKey = $second->viewData('requestKey');
        $this->assertNotSame($firstKey, $secondKey);
        $this->post(route('booking.store'), array_replace($this->bookingData(), ['request_key' => $firstKey]))->assertRedirect(route('booking.success'));
        $this->post(route('booking.store'), array_replace($this->bookingData('2026-10-19T12:00:00+02:00'), ['request_key' => $secondKey]))->assertRedirect(route('booking.success'));
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_form_reads_and_booking_submissions_have_separate_rate_limits(): void
    {
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->get(route('booking.form', ['date' => 'invalid']))->assertRedirect();
        }
        $this->get(route('booking.form', ['date' => 'invalid']))->assertStatus(429)->assertHeader('Retry-After');
        $this->post(route('booking.store'), $this->bookingData())->assertRedirect(route('booking.success'));
        $this->assertDatabaseCount('bookings', 1);
    }
}
