<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\OutboxMessage;
use App\Models\Salon;
use App\Services\NotificationSender;
use App\Services\ReviewRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReviewRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_is_delayed_limited_and_cancelled_by_signed_unsubscribe(): void
    {
        Salon::factory()->create(['features' => ['reviews' => true], 'review_url' => 'https://g.page/r/test/review', 'review_delay_hours' => 3]);
        $guest = Guest::factory()->create(['review_opt_in_at' => now()]);
        $booking = Booking::factory()->create(['guest_id' => $guest->id, 'status' => 'completed']);
        $reviews = app(ReviewRequests::class);
        $reviews->queue($booking);
        $message = OutboxMessage::firstOrFail();
        $this->assertSame('review', $message->kind);
        $this->assertSame(now()->addHours(3)->timestamp, $message->scheduled_at->timestamp);
        $other = Booking::factory()->create(['guest_id' => $guest->id, 'status' => 'completed']);
        $reviews->queue($other);
        $this->assertDatabaseCount('outbox_messages', 1);
        Mail::shouldReceive('send')->never();
        $this->assertSame(0, app(NotificationSender::class)->sendDue());
        $url = $reviews->unsubscribeUrl($guest);
        $response = $this->get($url)->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), '<main'));
        $this->assertNull($guest->fresh()->review_opt_out_at);
        $this->post($url)->assertOk()->assertSee('Leiratkoztál');
        $this->assertSame('obsolete', $message->fresh()->status);
        $this->assertNotNull($guest->fresh()->review_opt_out_at);
        $this->travel(4)->hours();
        $this->assertSame(0, app(NotificationSender::class)->sendDue());
    }

    public function test_no_consent_or_disabled_module_never_queues_review(): void
    {
        $salon = Salon::factory()->create(['features' => ['reviews' => true], 'review_url' => 'https://g.page/r/test/review']);
        $booking = Booking::factory()->create(['status' => 'completed']);
        app(ReviewRequests::class)->queue($booking);
        $this->assertDatabaseCount('outbox_messages', 0);
        $booking->guest->update(['review_opt_in_at' => now()]);
        $salon->update(['features' => []]);
        app(ReviewRequests::class)->queue($booking);
        $this->assertDatabaseCount('outbox_messages', 0);
        $this->post(route('reviews.unsubscribe.update', $booking->guest))->assertForbidden();
    }

    public function test_duplicate_cards_share_contact_frequency_limit_and_opt_out_without_merging(): void
    {
        Salon::factory()->create(['features' => ['reviews' => true], 'review_url' => 'https://g.page/r/test/review']);
        $first = Guest::factory()->create(['email' => 'same@example.test', 'review_opt_in_at' => now()]);
        $second = Guest::factory()->create(['email' => 'SAME@example.test', 'review_opt_in_at' => now()]);
        $one = Booking::factory()->create(['guest_id' => $first->id, 'status' => 'completed']);
        $two = Booking::factory()->create(['guest_id' => $second->id, 'status' => 'completed']);
        $reviews = app(ReviewRequests::class);
        $reviews->queue($one);
        $reviews->queue($two);
        $this->assertDatabaseCount('outbox_messages', 1);
        $reviews->unsubscribe($second);
        $this->assertNotNull($first->fresh()->review_opt_out_at);
        $this->assertDatabaseCount('guests', 2);
        $this->assertSame('obsolete', OutboxMessage::firstOrFail()->status);
    }

    public function test_due_review_uses_neutral_template_and_one_click_unsubscribe_headers(): void
    {
        Salon::factory()->create(['features' => ['reviews' => true], 'review_url' => 'https://g.page/r/test/review', 'review_delay_hours' => 0]);
        $booking = Booking::factory()->create(['status' => 'completed', 'guest_id' => Guest::factory()->create(['review_opt_in_at' => now()])->id]);
        app(ReviewRequests::class)->queue($booking);
        $this->assertSame(1, app(NotificationSender::class)->sendDue());
        $mail = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertStringContainsString('/ertekeles/lemondas/', $mail->getHeaders()->get('List-Unsubscribe')->getBodyAsString());
        $this->assertSame('List-Unsubscribe=One-Click', $mail->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString());
        $this->assertStringContainsString('Minden őszinte visszajelzés egyformán értékes.', $mail->getTextBody());
        $this->assertStringContainsString('/ertekeles/lemondas/', $mail->getTextBody());
    }
}
