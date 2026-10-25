<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Services\SmsNotificationSender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhoneBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_only_booking_sends_management_link_and_rebooks_same_guest(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        Salon::factory()->create(['features' => ['phone_booking' => true, 'sms' => true], 'contact_mode' => 'phone']);
        config(['services.sms' => ['provider' => 'twilio', 'account_sid' => 'AC'.str_repeat('a', 32), 'auth_token' => 'fake', 'sender' => '+36111111111']]);
        Http::preventStrayRequests();
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM'.str_repeat('a', 32)], 201)]);
        $service = Service::factory()->create();
        $staff = $service->staff()->first();
        $data = ['service_id' => $service->id, 'slot' => $staff->id.'|2026-10-19T10:00:00+02:00', 'name' => 'Telefonos minta', 'phone' => '06301234567', 'terms' => 1, 'request_key' => Str::uuid()->toString()];
        $this->post(route('booking.store'), $data)->assertRedirect(route('booking.success'));
        $booking = Booking::firstOrFail();
        $this->assertNull($booking->guest->email);
        $this->get(route('booking.success'))->assertOk()->assertSee('SMS-visszaigazolás')->assertSee(route('booking.manage', $booking->manage_token));
        $this->assertDatabaseHas('sms_outbox_messages', ['booking_id' => $booking->id, 'kind' => 'confirmation']);
        $this->assertSame(1, app(SmsNotificationSender::class)->sendDue());
        Http::assertSent(fn ($request) => $request['To'] === '+36301234567' && str_contains($request['Body'], route('booking.manage', $booking->manage_token)));
        $this->get(route('booking.rebook.form', $booking->manage_token))->assertOk();
        $this->post(route('booking.rebook.store', $booking->manage_token), array_replace($data, ['slot' => $staff->id.'|2026-10-19T14:00:00+02:00', 'request_key' => Str::uuid()->toString()]))->assertRedirect(route('booking.success'));
        $this->assertDatabaseCount('guests', 1);
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_both_mode_requires_both_contacts_and_rejects_invalid_phone(): void
    {
        Salon::factory()->create(['features' => ['phone_booking' => true], 'contact_mode' => 'both']);
        $response = $this->postJson(route('booking.store'), ['phone' => '123']);
        $response->assertJsonValidationErrors('email');
    }

    public function test_invalid_required_phone_has_the_exact_hungarian_validation_message(): void
    {
        Salon::factory()->create(['features' => ['phone_booking' => true], 'contact_mode' => 'phone']);
        $service = Service::factory()->create();
        $response = $this->postJson(route('booking.store'), [
            'service_id' => $service->id, 'slot' => $service->staff()->first()->id.'|2026-10-26T10:00:00+01:00',
            'name' => 'Telefonos próba', 'phone' => 'abc', 'terms' => 1, 'request_key' => Str::uuid()->toString(),
        ]);
        $response->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->assertSame('Érvényes, országkóddal megadható telefonszám szükséges.', $response->json('errors.phone.0'));
        $this->assertStringNotContainsString('?', $response->json('errors.phone.0'));
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_phone_booking_without_queued_confirmation_still_exposes_its_private_management_link_to_its_session(): void
    {
        Salon::factory()->create(['features' => ['phone_booking' => true], 'contact_mode' => 'phone']);
        $booking = Booking::factory()->create(['guest_id' => Guest::factory()->create(['email' => null, 'phone' => '+36305550001'])->id]);
        $this->withSession(['last_booking' => $booking->id])->get(route('booking.success'))
            ->assertOk()->assertSee('Automatikus visszaigazolás nem küldhető')
            ->assertSee(route('booking.manage', $booking->manage_token))->assertDontSee('küldése sorba került')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->flushSession();
        $this->get(route('booking.success'))->assertNotFound();
    }
}
