<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\OutboxMessage;
use App\Models\Salon;
use App\Models\Service;
use App\Services\NotificationSender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PilotPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_email_has_salon_sender_reply_to_and_real_plain_text_part(): void
    {
        $salon = Salon::factory()->create(['name' => 'Rózsa & Fésű', 'email' => 'salon@example.test']);
        $booking = Booking::factory()->create(['revision' => 1, 'price' => 0]);
        OutboxMessage::factory()->create(['booking_id' => $booking->id]);
        config(['mail.default' => 'array', 'mail.from.address' => 'verified@example.test']);
        $this->assertSame(1, app(NotificationSender::class)->sendDue());
        $mail = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame('verified@example.test', $mail->getFrom()[0]->getAddress());
        $this->assertSame($salon->name, $mail->getFrom()[0]->getName());
        $this->assertSame($salon->email, $mail->getReplyTo()[0]->getAddress());
        $this->assertStringContainsString('Rózsa & Fésű', $mail->getTextBody());
        $this->assertStringContainsString('Ingyenes', $mail->getTextBody());
        $this->assertStringContainsString(route('booking.manage', $booking->manage_token), $mail->getTextBody());
        $this->assertStringNotContainsString('<html', $mail->getTextBody());
        $this->assertStringContainsString('lang="hu" dir="ltr"', $mail->getHtmlBody());
    }

    public function test_notify_reports_the_failed_attempt_without_leaking_transport_details(): void
    {
        Salon::factory()->create();
        $booking = Booking::factory()->create(['revision' => 1]);
        OutboxMessage::factory()->create(['booking_id' => $booking->id]);
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('private-provider-detail'));
        $this->artisan('salon:notify')->expectsOutput('0 értesítés feldolgozva. Hibás küldési próbálkozás: 1.')->assertSuccessful();
        $this->assertDatabaseHas('outbox_messages', ['booking_id' => $booking->id, 'status' => 'retry', 'error_code' => 'RuntimeException']);
    }

    public function test_guest_sees_free_price_horizon_explanation_and_cancellation_copy(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00', 'Europe/Budapest'));
        Salon::factory()->create(['horizon_days' => 7]);
        $service = Service::factory()->create(['price' => 0]);
        $this->get(route('home'))->assertOk()->assertSee('Ingyenes');
        $this->get(route('booking.form', ['service_id' => $service->id]))->assertOk()->assertSee('Ingyenes');
        $this->get(route('booking.form', ['date' => '2026-11-03']))->assertOk()->assertSee('Legfeljebb 7 napra előre')->assertSee('2026. 11. 02.')->assertDontSee('type="radio"', false);
        $booking = Booking::factory()->create(['revision' => 1]);
        $this->from(route('booking.manage', $booking->manage_token))->post(route('booking.update', $booking->manage_token), ['action' => 'cancel', 'revision' => 1])->assertRedirect()->assertSessionHas('success', 'A foglalásodat lemondtad. Várunk egy másik időpontban!');
        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_backup_pruning_is_scoped_can_preview_and_keeps_the_latest_even_when_old(): void
    {
        $directory = storage_path('framework/prune-test-'.Str::uuid());
        File::ensureDirectoryExists($directory);
        config(['salon.backup_directory' => $directory]);
        $expired = $directory.'/20260101-020000-aaaaaaaa.backup';
        $latest = $directory.'/20260201-020000-bbbbbbbb.backup';
        $unrelated = $directory.'/custom-export.backup';
        foreach ([$expired, $latest, $unrelated] as $index => $path) {
            File::put($path, 'test-backup');
            touch($path, now()->subDays(90 - $index)->timestamp);
        }
        try {
            $this->artisan('salon:prune-backups', ['--days' => 30, '--dry-run' => true])->assertSuccessful();
            $this->assertFileExists($expired);
            $this->artisan('salon:prune-backups', ['--days' => 0])->assertFailed();
            $this->assertFileExists($expired);
            $this->artisan('salon:prune-backups', ['--days' => 30])->assertSuccessful();
            $this->assertFileDoesNotExist($expired);
            $this->assertFileExists($latest);
            $this->assertFileExists($unrelated);
        } finally {
            File::delete([$expired, $latest, $unrelated]);
            rmdir($directory);
        }
    }
}
