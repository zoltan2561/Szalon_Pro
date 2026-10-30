<?php

namespace Tests\Feature;

use App\Models\OutboxMessage;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingService;
use App\Services\NotificationSender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class BusinessBookingEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_salon_and_worker_address_gets_one_email_without_guest_management_tokens(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        Salon::factory()->create(['email' => 'team@example.test']);
        $service = Service::factory()->create();
        $staff = $service->staff()->firstOrFail();
        User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id, 'email' => 'TEAM@example.test']);
        $booking = app(BookingService::class)->create(['service_id' => $service->id, 'staff_id' => $staff->id, 'starts_at' => '2026-10-19T10:00:00+02:00', 'name' => 'Telefonos vendég', 'email' => null, 'phone' => '+36305550001', 'request_key' => Str::uuid()->toString()]);
        $this->assertDatabaseHas('outbox_messages', ['booking_id' => $booking->id, 'kind' => 'new_booking', 'status' => 'pending']);
        Mail::shouldReceive('send')->once()->andReturnUsing(function (array $views, array $data, callable $configure) use ($booking): void {
            $mail = new Message(new Email);
            $configure($mail);
            $this->assertSame(['team@example.test'], array_map(fn ($address): string => $address->getAddress(), $mail->getSymfonyMessage()->getTo()));
            foreach ([view($views['html'], $data)->render(), view($views['text'], $data)->render()] as $body) {
                $this->assertStringContainsString(route('admin.booking', $booking), $body);
                $this->assertStringContainsString('Telefonos vendég', $body);
                $this->assertStringNotContainsString($booking->manage_token, $body);
            }
        });
        $this->assertSame(1, app(NotificationSender::class)->sendDue());
        $this->assertSame(0, app(NotificationSender::class)->sendDue());
        $this->assertSame('sent', OutboxMessage::where('kind', 'new_booking')->first()->status);
    }

    public function test_distinct_business_addresses_and_guest_confirmation_are_kept_separate(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        Salon::factory()->create(['email' => 'salon@example.test']);
        $service = Service::factory()->create();
        $staff = $service->staff()->firstOrFail();
        User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id, 'email' => 'worker@example.test']);
        $booking = app(BookingService::class)->create(['service_id' => $service->id, 'staff_id' => $staff->id, 'starts_at' => '2026-10-19T10:00:00+02:00', 'name' => 'Vendég', 'email' => 'guest@example.test', 'request_key' => Str::uuid()->toString()]);
        OutboxMessage::where('kind', 'reminder')->delete();
        $deliveries = [];
        Mail::shouldReceive('send')->twice()->andReturnUsing(function (array $views, array $data, callable $configure) use (&$deliveries): void {
            $mail = new Message(new Email);
            $configure($mail);
            $deliveries[$data['kind']] = array_map(fn ($address): string => $address->getAddress(), $mail->getSymfonyMessage()->getTo());
        });
        $this->assertSame(2, app(NotificationSender::class)->sendDue());
        $this->assertSame(['guest@example.test'], $deliveries['confirmation']);
        $this->assertSame(['salon@example.test', 'worker@example.test'], $deliveries['new_booking']);
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_pending_business_alert_uses_the_updated_time_if_the_booking_moves_before_delivery(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        Salon::factory()->create(['email' => 'salon@example.test']);
        $service = Service::factory()->create();
        $staff = $service->staff()->firstOrFail();
        $owner = User::factory()->create(['role' => 'owner']);
        $booking = app(BookingService::class)->create(['service_id' => $service->id, 'staff_id' => $staff->id, 'starts_at' => '2026-10-19T10:00:00+02:00', 'name' => 'Vendég', 'email' => null, 'request_key' => Str::uuid()->toString()]);
        app(BookingService::class)->change($booking, ['action' => 'move', 'revision' => 1, 'staff_id' => $staff->id, 'starts_at' => '2026-10-19T14:00:00+02:00'], $owner);
        $this->assertDatabaseHas('outbox_messages', ['booking_id' => $booking->id, 'kind' => 'new_booking', 'status' => 'pending']);
        Mail::shouldReceive('send')->once()->andReturnUsing(function (array $views, array $data): void {
            $this->assertSame('new_booking', $data['kind']);
            $this->assertSame('14:00', $data['booking']->starts_at->setTimezone('Europe/Budapest')->format('H:i'));
        });
        $this->assertSame(1, app(NotificationSender::class)->sendDue());
    }

    public function test_interrupted_business_alert_is_recovered_after_the_booking_moves(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        Salon::factory()->create(['email' => 'salon@example.test']);
        $service = Service::factory()->create();
        $staff = $service->staff()->firstOrFail();
        $owner = User::factory()->create(['role' => 'owner']);
        $booking = app(BookingService::class)->create(['service_id' => $service->id, 'staff_id' => $staff->id, 'starts_at' => '2026-10-19T10:00:00+02:00', 'name' => 'Vendég', 'email' => null, 'request_key' => Str::uuid()->toString()]);
        $message = OutboxMessage::where('kind', 'new_booking')->firstOrFail();
        $message->update(['status' => 'sending', 'attempts' => 1, 'updated_at' => now()->subMinutes(6)]);
        app(BookingService::class)->change($booking, ['action' => 'move', 'revision' => 1, 'staff_id' => $staff->id, 'starts_at' => '2026-10-19T14:00:00+02:00'], $owner);
        Mail::shouldReceive('send')->once()->andReturnUsing(function (array $views, array $data): void {
            $this->assertSame('14:00', $data['booking']->starts_at->setTimezone('Europe/Budapest')->format('H:i'));
        });
        $this->assertSame(1, app(NotificationSender::class)->sendDue());
        $this->assertSame('sent', $message->fresh()->status);
    }
}
