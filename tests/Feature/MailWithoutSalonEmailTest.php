<?php

namespace Tests\Feature;

use App\Models\OutboxMessage;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Notifications\PasswordResetLink;
use App\Services\BookingService;
use App\Services\NotificationSender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class MailWithoutSalonEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_salon_without_contact_email_can_still_send_the_password_mails(): void
    {
        Salon::factory()->create(['email' => '', 'name' => 'Új Szalon']);
        $owner = User::factory()->create(['role' => 'owner']);

        foreach ([new PasswordResetLink(str_repeat('a', 64)), new PasswordChanged] as $notification) {
            $message = $notification->toMail($owner);
            $this->assertSame([], $message->replyTo, 'No Reply-To header without a salon contact address.');
            $this->assertSame('Új Szalon', $message->from[1]);
            $this->assertNotEmpty((string) $message->render());
        }

        $owner->notify(new PasswordResetLink(str_repeat('b', 64)));
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_the_reply_to_header_is_kept_when_the_salon_has_a_contact_email(): void
    {
        Salon::factory()->create(['email' => 'szalon@example.test', 'name' => 'Teljes Szalon']);

        $message = (new PasswordResetLink(str_repeat('c', 64)))->toMail(User::factory()->create(['role' => 'owner']));

        $this->assertSame([['szalon@example.test', 'Teljes Szalon']], $message->replyTo);
    }

    public function test_booking_mails_are_sent_without_a_reply_to_when_the_salon_email_is_empty(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        Salon::factory()->create(['email' => '']);
        $service = Service::factory()->create();
        $staff = $service->staff()->firstOrFail();
        app(BookingService::class)->create(['service_id' => $service->id, 'staff_id' => $staff->id, 'starts_at' => '2026-10-19T10:00:00+02:00', 'name' => 'Vendég', 'email' => 'vendeg@example.test', 'phone' => null, 'request_key' => Str::uuid()->toString()]);
        Mail::shouldReceive('send')->atLeast()->once()->andReturnUsing(function (array $views, array $data, callable $configure): void {
            $mail = new Message(new Email);
            $configure($mail);
            $this->assertSame([], $mail->getSymfonyMessage()->getReplyTo());
        });

        $this->assertGreaterThanOrEqual(1, app(NotificationSender::class)->sendDue());
        $this->assertSame(0, OutboxMessage::whereIn('status', ['pending', 'retry', 'failed'])->where('scheduled_at', '<=', now())->count());
    }
}
