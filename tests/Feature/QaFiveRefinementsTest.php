<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use App\Services\BookingService;
use App\Services\Branding;
use App\Services\NotificationSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QaFiveRefinementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_new_staff_does_not_replace_names_in_either_staff_selector(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner', 'email' => 'taken@example.test']);
        $people = Staff::factory()->sequence(['name' => 'Anna Teszt'], ['name' => 'Béla Teszt'])->count(2)->create();
        $this->actingAs($owner)->from(route('admin.staff'))->post(route('admin.staff.save'), ['name' => 'Hibás Új Név', 'email' => $owner->email, 'password' => 'private-test-password', 'hours' => [1 => ['start' => '09:00', 'end' => '18:00']]])->assertSessionHasErrors('email');
        $html = $this->get(route('admin.staff'))->assertOk()->assertSee('value="Hibás Új Név"', false)->getContent();
        foreach ($people as $person) {
            $this->assertSame(2, substr_count($html, '<option value="'.$person->id.'">'.$person->name.'</option>'));
        }
        $this->assertSame(2, Staff::count());
    }

    public function test_dark_custom_accent_and_paper_foregrounds_are_readable_for_every_theme(): void
    {
        $branding = app(Branding::class);
        foreach (array_keys(config('branding.presets')) as $theme) {
            $salon = Salon::factory()->make(['theme' => $theme, 'accent' => '#6d2734']);
            $brand = $branding->forSalon($salon);
            $this->assertGreaterThanOrEqual(4.5, $branding->contrast($brand['accent'], $brand['on_accent']));
            $this->assertGreaterThanOrEqual(4.5, $branding->contrast($brand['paper'], $brand['ink']));
            $this->assertGreaterThanOrEqual(4.5, $branding->contrast($brand['surface'], $brand['ink']));
        }
    }

    public function test_guest_and_owner_cancellation_mail_identifies_the_actual_actor_in_both_parts(): void
    {
        config(['mail.default' => 'array']);
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        foreach ([null, $owner] as $actor) {
            $booking = Booking::factory()->create(['starts_at' => now()->addDays(3)])->refresh();
            app(BookingService::class)->change($booking, ['action' => 'cancel', 'revision' => $booking->revision], $actor);
            $this->assertSame(1, app(NotificationSender::class)->sendDue());
            $mail = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
            foreach ([$mail->getTextBody(), $mail->getHtmlBody()] as $body) {
                $this->assertStringContainsString($actor ? 'A foglalásodat lemondtuk' : 'Lemondtad a foglalásodat', $body);
                if ($actor) {
                    $this->assertStringNotContainsString('Lemondtad a foglalásodat', $body);
                }
            }
        }
    }

    public function test_cancelled_management_page_hides_terms_but_confirmed_page_keeps_them(): void
    {
        $salon = Salon::factory()->create(['terms' => 'Egyedi 24 órás szabály']);
        $booking = Booking::factory()->create();
        $this->get(route('booking.manage', $booking->manage_token))->assertOk()->assertSee($salon->terms);
        $booking->update(['status' => 'cancelled']);
        $this->get(route('booking.manage', $booking->manage_token))->assertOk()->assertDontSee($salon->terms)->assertSee('Új időpont foglalása');
    }

    public function test_rights_error_explains_acceptance_and_reselecting_uploaded_files(): void
    {
        Storage::fake('public');
        $salon = Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $data = $salon->fresh()->only(['name', 'tagline', 'about', 'email', 'phone', 'address', 'theme', 'accent', 'font', 'terms', 'privacy', 'min_notice_minutes', 'horizon_days', 'cancel_notice_hours', 'reminder_hours', 'retention_days']) + ['hours' => [1 => ['start' => '09:00', 'end' => '18:00']], 'hero_image' => UploadedFile::fake()->image('photo.png')];
        $this->actingAs($owner)->from(route('admin.settings'))->post(route('admin.settings.save'), $data)->assertSessionHasErrors(['content_rights' => 'Fogadd el a felhasználási jogosultságot.']);
        $this->followingRedirects()->post(route('admin.settings.save'), $data)->assertOk()->assertSee('Válaszd ki újra a feltöltendő képeket.');
        $this->assertNull($salon->fresh()->hero_image);
    }

    public function test_missing_password_confirmation_has_clear_hungarian_message(): void
    {
        $this->post(route('password.update'), ['email' => 'owner@example.test', 'token' => str_repeat('a', 64), 'password' => 'private-new-password'])->assertSessionHasErrors(['password_confirmation' => 'Add meg újra az új jelszót.']);
    }

    public function test_scheduler_processes_password_requests_without_a_separate_daemon(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('queue:work database --queue=password-resets --stop-when-empty --max-time=50 --tries=1')->assertSuccessful();
    }
}
