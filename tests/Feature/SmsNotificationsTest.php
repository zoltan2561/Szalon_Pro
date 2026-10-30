<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\Service;
use App\Models\SmsOutboxMessage;
use App\Models\User;
use App\Services\BookingService;
use App\Services\SmsNotificationSender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SmsNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00+00:00'));
        Salon::factory()->create(['features' => ['sms' => true]]);
        config()->set('services.sms', [
            'provider' => 'twilio',
            'account_sid' => 'AC'.str_repeat('a', 32),
            'auth_token' => 'fake-token',
            'sender' => '+36111111111',
        ]);
        Http::preventStrayRequests();
    }

    public function test_phone_only_booking_queues_reminder_and_provider_acceptance_is_recorded(): void
    {
        $service = Service::factory()->create();
        $booking = app(BookingService::class)->create([
            'service_id' => $service->id,
            'staff_id' => $service->staff()->firstOrFail()->id,
            'starts_at' => '2026-10-19T10:00:00+02:00',
            'name' => 'Telefonos Vendég',
            'email' => null,
            'phone' => '06 30 123 4567',
            'sms_opt_in' => true,
            'request_key' => (string) Str::uuid(),
        ]);

        $this->assertDatabaseCount('outbox_messages', 1);
        $this->assertDatabaseHas('outbox_messages', ['booking_id' => $booking->id, 'kind' => 'new_booking']);
        $this->assertDatabaseMissing('outbox_messages', ['booking_id' => $booking->id, 'kind' => 'confirmation']);
        $message = SmsOutboxMessage::sole();
        $this->assertSame('reminder', $message->kind);
        $this->assertSame('pending', $message->status);

        Http::fake(['https://api.twilio.com/*' => Http::response(['sid' => 'SM'.str_repeat('b', 32)], 201)]);
        $this->travelTo($message->scheduled_at->addSecond());
        $this->assertSame(1, app(SmsNotificationSender::class)->sendDue());
        $this->assertSame('sent', $message->fresh()->status);
        $this->assertSame('SM'.str_repeat('b', 32), $message->fresh()->provider_id);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC'.str_repeat('a', 32).'/Messages.json'
            && $request['To'] === '+36301234567'
            && str_contains($request['Body'], '2026.10.19. 10:00'));
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_disabling_module_or_missing_provider_never_queues_or_sends(): void
    {
        $service = Service::factory()->create();
        Salon::current()->update(['features' => ['sms' => false]]);
        $this->create($service, '2026-10-19T10:00:00+02:00');
        $this->assertDatabaseCount('sms_outbox_messages', 0);

        Salon::current()->update(['features' => ['sms' => true]]);
        config()->set('services.sms.provider', 'disabled');
        $this->create($service, '2026-10-19T13:00:00+02:00');
        $this->assertDatabaseCount('sms_outbox_messages', 0);
        Http::assertNothingSent();
    }

    public function test_uncertain_provider_response_is_not_automatically_retried(): void
    {
        $service = Service::factory()->create();
        $this->create($service, '2026-10-19T10:00:00+02:00');
        $message = SmsOutboxMessage::sole();
        $this->travelTo($message->scheduled_at->addSecond());
        Http::fake(['https://api.twilio.com/*' => Http::response([], 503)]);

        $this->assertSame(0, app(SmsNotificationSender::class)->sendDue());
        $this->assertSame('uncertain', $message->fresh()->status);
        $this->assertSame(1, $message->fresh()->attempts);
        $this->assertSame(0, app(SmsNotificationSender::class)->sendDue());
        Http::assertSentCount(1);
    }

    public function test_opt_in_is_only_offered_when_the_module_and_provider_are_ready(): void
    {
        Service::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);

        $this->get(route('booking.form'))->assertOk()->assertSee('name="sms_opt_in"', false);
        $this->actingAs($owner)->get(route('admin.create'))->assertOk()->assertSee('name="sms_opt_in"', false);
        $this->get(route('admin.notifications'))->assertOk()->assertSee('SMS-emlékeztetők');

        config()->set('services.sms.provider', 'disabled');
        $this->get(route('admin.create'))->assertOk()->assertDontSee('name="sms_opt_in"', false);
        $this->get(route('booking.form'))->assertOk()->assertDontSee('name="sms_opt_in"', false);
    }

    private function create(Service $service, string $start): void
    {
        app(BookingService::class)->create([
            'service_id' => $service->id,
            'staff_id' => $service->staff()->firstOrFail()->id,
            'starts_at' => $start,
            'name' => 'SMS vendég',
            'email' => null,
            'phone' => '06301234567',
            'sms_opt_in' => true,
            'request_key' => (string) Str::uuid(),
        ]);
    }
}
