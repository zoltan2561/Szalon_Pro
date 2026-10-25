<?php

namespace Tests\Feature;

use App\Console\Commands\BackupSalon;
use App\Models\Booking;
use App\Models\GoogleCalendarBusy;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEvent;
use App\Models\Guest;
use App\Models\GuestPass;
use App\Models\LoyaltyEntry;
use App\Models\OutboxMessage;
use App\Models\PassPlan;
use App\Models\PassUse;
use App\Models\Salon;
use App\Models\Service;
use App\Models\SmsOutboxMessage;
use App\Models\Staff;
use App\Models\TimeBlock;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WorkException;
use App\Services\Availability;
use App\Services\BookingService;
use App\Services\NotificationSender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalonWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_transport_runs_without_holding_the_booking_transaction(): void
    {
        $booking = $this->create();
        $baseline = DB::transactionLevel();
        Mail::shouldReceive('send')->once()->andReturnUsing(function () use ($baseline): void {
            $this->assertSame($baseline, DB::transactionLevel());
            $this->assertDatabaseHas('outbox_messages', ['kind' => 'confirmation', 'status' => 'sending']);
        });
        $this->assertSame(1, app(NotificationSender::class)->sendDue());
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_abandoned_notification_claim_is_recovered_after_five_minutes(): void
    {
        $this->create();
        $message = OutboxMessage::where('kind', 'confirmation')->first();
        $message->update(['status' => 'sending', 'attempts' => 1, 'updated_at' => now()->subMinutes(6)]);
        Mail::shouldReceive('send')->once()->andReturn(null);
        $this->assertSame(1, app(NotificationSender::class)->sendDue());
        $this->assertSame(2, $message->fresh()->attempts);
        $this->assertSame('sent', $message->fresh()->status);
    }

    private Service $service;

    private Staff $staff;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00+00:00'));
        Salon::factory()->create();
        $this->service = Service::factory()->create();
        $this->staff = $this->service->staff()->first();
        $this->owner = User::factory()->create(['role' => 'owner', 'can_manage_bookings' => true]);
    }

    private function data(array $overrides = []): array
    {
        return array_merge(['service_id' => $this->service->id, 'staff_id' => $this->staff->id, 'starts_at' => '2026-10-19T10:00:00+02:00', 'name' => 'Teszt Vendég', 'email' => 'guest@example.test', 'phone' => '+36305550001', 'request_key' => Str::uuid()->toString()], $overrides);
    }

    private function create(array $overrides = []): Booking
    {
        return app(BookingService::class)->create($this->data($overrides));
    }

    private function httpData(array $overrides = []): array
    {
        $data = $this->data($overrides);
        $data['slot'] = $data['staff_id'].'|'.$data['starts_at'];
        unset($data['staff_id'],$data['starts_at']);

        return $data + ['terms' => 1];
    }

    public function test_public_booking_is_persisted_and_repeated_submission_is_idempotent(): void
    {
        $data = $this->httpData();
        $this->post(route('booking.store'), $data)->assertRedirect(route('booking.success'));
        $this->post(route('booking.store'), $data)->assertRedirect(route('booking.success'));
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('guests', 1);
        $this->assertDatabaseCount('outbox_messages', 2);
        $this->get(route('booking.success'))->assertOk()->assertSee('Várunk szeretettel!');
    }

    public function test_reusing_key_for_different_payload_is_rejected(): void
    {
        $data = $this->data();
        app(BookingService::class)->create($data);
        $this->expectException(ValidationException::class);
        app(BookingService::class)->create(array_merge($data, ['name' => 'Másik Vendég']));
    }

    public function test_manual_and_public_booking_use_the_same_conflict_guard(): void
    {
        $this->create();
        $this->actingAs($this->owner)->from(route('admin.create'))->post(route('admin.store'), $this->httpData())->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_buffer_and_working_hours_are_checked(): void
    {
        $this->create();
        $engine = app(Availability::class);
        $this->assertFalse($engine->canBook($this->service, $this->staff, CarbonImmutable::parse('2026-10-19T11:00:00+02:00')->utc()));
        $this->assertTrue($engine->canBook($this->service, $this->staff, CarbonImmutable::parse('2026-10-19T11:15:00+02:00')->utc()));
        $this->assertFalse($engine->canBook($this->service, $this->staff, CarbonImmutable::parse('2026-10-19T17:00:00+02:00')->utc()));
        $this->service->update(['buffer_before' => 15]);
        $this->assertFalse($engine->canBook($this->service, $this->staff, CarbonImmutable::parse('2026-10-20T09:00:00+02:00')->utc()));
    }

    public function test_break_blocks_holiday_and_daily_override_are_respected(): void
    {
        $engine = app(Availability::class);
        $this->staff->update(['weekly_hours' => array_fill(1, 7, [['09:00', '12:00'], ['13:00', '18:00']])]);
        $time = CarbonImmutable::parse('2026-10-19T11:00:00+02:00')->utc();
        $this->assertFalse($engine->canBook($this->service, $this->staff, $time));
        WorkException::factory()->create(['staff_id' => $this->staff->id, 'date' => '2026-10-19', 'hours' => []]);
        $this->assertSame([], $engine->slots($this->service, '2026-10-19'));
        WorkException::where('staff_id', $this->staff->id)->update(['hours' => json_encode([['10:00', '12:00']])]);
        $this->assertTrue($engine->canBook($this->service, $this->staff, $time->subHour()));
        TimeBlock::factory()->create(['staff_id' => $this->staff->id, 'starts_at' => $time->subHour(), 'ends_at' => $time->addHours(2)]);
        $this->assertSame([], $engine->slots($this->service, '2026-10-19'));
    }

    public function test_failed_move_preserves_original_and_revision(): void
    {
        $a = $this->create();
        $this->create(['starts_at' => '2026-10-19T13:00:00+02:00']);
        try {
            app(BookingService::class)->change($a, ['revision' => 1, 'action' => 'move', 'staff_id' => $this->staff->id, 'starts_at' => '2026-10-19T13:00:00+02:00'], $this->owner);
            $this->fail('Conflict expected');
        } catch (ValidationException) {
        }
        $this->assertSame('08:00', $a->fresh()->starts_at->format('H:i'));
        $this->assertSame(1, $a->fresh()->revision);
    }

    public function test_price_and_duration_snapshots_survive_catalog_changes_and_archiving(): void
    {
        $booking = $this->create();
        $this->service->update(['name' => 'Új név', 'price' => 19000, 'duration_minutes' => 90, 'active' => false]);
        $booking->refresh();
        $this->assertSame(9500, $booking->price);
        $this->assertSame(60, $booking->duration_minutes);
        $this->assertSame('Teszt szolgáltatás', $booking->service_name);
        $this->assertSame([], app(Availability::class)->slots($this->service, '2026-10-19'));
        $this->actingAs($this->owner)->get(route('admin.booking', $booking))->assertOk();
    }

    public function test_move_invalidates_old_notifications_and_stale_requests(): void
    {
        $booking = $this->create();
        $moved = app(BookingService::class)->change($booking, ['revision' => 1, 'action' => 'move', 'staff_id' => $this->staff->id, 'starts_at' => '2026-10-20T10:00:00+02:00'], $this->owner);
        $this->assertSame(2, $moved->revision);
        $this->assertSame(2, OutboxMessage::where('status', 'obsolete')->count());
        $this->assertSame(2, OutboxMessage::where('revision', 2)->where('status', 'pending')->count());
        $this->expectException(ValidationException::class);
        app(BookingService::class)->change($booking, ['revision' => 1, 'action' => 'cancel'], $this->owner);
    }

    public function test_cancellation_frees_slot_and_never_sends_old_reminder(): void
    {
        $booking = $this->create();
        app(BookingService::class)->change($booking, ['revision' => 1, 'action' => 'cancel']);
        Mail::shouldReceive('send')->once()->andReturn(null);
        $this->assertSame(1, app(NotificationSender::class)->sendDue());
        $this->assertDatabaseHas('outbox_messages', ['kind' => 'reminder', 'status' => 'obsolete']);
        $this->assertTrue(app(Availability::class)->canBook($this->service, $this->staff, $booking->starts_at));
    }

    public function test_mail_failure_keeps_booking_and_retries_without_recreating_it(): void
    {
        $booking = $this->create();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('private provider details'));
        $this->assertSame(0, app(NotificationSender::class)->sendDue());
        $message = OutboxMessage::where('kind', 'confirmation')->first();
        $this->assertSame('retry', $message->status);
        $this->assertSame('RuntimeException', $message->error_code);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->travel(3)->minutes();
        Mail::shouldReceive('send')->once()->andReturn(null);
        $this->assertSame(1, app(NotificationSender::class)->sendDue());
        $this->assertDatabaseCount('bookings', 1);
        $this->assertSame('sent', $message->fresh()->status);
    }

    public function test_short_notice_booking_has_confirmation_but_no_reminder(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19T05:00:00+00:00'));
        $this->create();
        $this->assertDatabaseCount('outbox_messages', 1);
    }

    public function test_staff_can_only_view_own_calendar_guests_and_authorized_notes(): void
    {
        $own = $this->create();
        $other = Booking::factory()->create(['starts_at' => $own->starts_at]);
        $staff = User::factory()->create(['role' => 'staff', 'staff_id' => $this->staff->id, 'can_manage_bookings' => false, 'can_view_notes' => false]);
        $this->actingAs($staff)->get(route('admin.booking', $own))->assertOk();
        $this->get(route('admin.booking', $other))->assertForbidden();
        $this->get(route('admin.guest', $other->guest))->assertForbidden();
        $this->get(route('admin.settings'))->assertForbidden();
        $this->post(route('admin.notes', $own->guest), ['notes' => 'Titkos'])->assertForbidden();
        $this->post(route('admin.booking.update', $own), ['revision' => 1, 'action' => 'cancel'])->assertForbidden();
        $this->get(route('admin.guests'))->assertSee($own->guest->email)->assertDontSee($other->guest->email);
    }

    public function test_guests_are_never_merged_by_name_or_contact(): void
    {
        $this->create();
        $this->create(['starts_at' => '2026-10-20T10:00:00+02:00']);
        $this->assertDatabaseCount('guests', 2);
        $this->actingAs($this->owner)->get(route('admin.guest', Guest::first()))->assertSee('nem vonja össze');
    }

    public function test_management_link_is_secret_expiring_and_read_only(): void
    {
        $booking = $this->create();
        $this->assertNotSame($booking->manage_token, $booking->getRawOriginal('manage_token'));
        $this->get(route('booking.manage', $booking->manage_token))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertSame(1, $booking->fresh()->revision);
        $this->get('/kezeles/1')->assertNotFound();
        $booking->update(['token_expires_at' => now()->subMinute()]);
        $this->get(route('booking.manage', $booking->manage_token))->assertNotFound();
    }

    public function test_guest_cancellation_deadline_and_staff_archive_are_enforced(): void
    {
        $booking = $this->create();
        $this->travelTo($booking->starts_at->subHours(23));
        $this->post(route('booking.update', $booking->manage_token), ['action' => 'cancel', 'revision' => 1])->assertSessionHasErrors('starts_at');
        $this->assertSame('confirmed', $booking->fresh()->status);
        $staff = User::factory()->create(['staff_id' => $this->staff->id, 'role' => 'staff']);
        $this->staff->update(['active' => false]);
        $this->actingAs($staff)->get(route('admin.today'))->assertForbidden();
    }

    public function test_timezone_offsets_and_daylight_saving_are_handled_in_utc(): void
    {
        $engine = app(Availability::class);
        $this->service->update(['duration_minutes' => 20, 'buffer_after' => 0]);
        $hours = array_fill(1, 7, [['01:00', '05:00']]);
        Salon::current()->update(['opening_hours' => $hours]);
        $this->staff->update(['weekly_hours' => $hours]);
        $this->travelTo(CarbonImmutable::parse('2026-10-23T00:00:00+00:00'));
        $slots = $engine->slots($this->service, '2026-10-25');
        $two = array_values(array_filter($slots, fn ($s) => $s['label'] === '02:00'));
        $this->assertCount(2, $two);
        $this->assertNotSame($two[0]['offset'], $two[1]['offset']);
        $this->assertSame(3600, abs($engine->parse($two[0]['starts_at'])->getTimestamp() - $engine->parse($two[1]['starts_at'])->getTimestamp()));
        $this->travelTo(CarbonImmutable::parse('2027-03-26T00:00:00+00:00'));
        $spring = $engine->slots($this->service, '2027-03-28');
        $this->assertCount(0, array_filter($spring, fn ($s) => str_starts_with($s['label'], '02:')));
        $this->assertTrue($engine->parse('2027-03-28T10:00:00+02:00')->equalTo($engine->parse('2027-03-28T04:00:00-04:00')));
    }

    public function test_minimum_notice_horizon_and_ineligible_staff_are_enforced(): void
    {
        $engine = app(Availability::class);
        $this->assertFalse($engine->canBook($this->service, $this->staff, now()->toImmutable()->addHour()));
        $this->assertSame([], $engine->slots($this->service, '2027-01-01'));
        $this->assertSame([], $engine->slots($this->service, '2026-02-30'));
        $other = Staff::factory()->create();
        $this->assertFalse($engine->canBook($this->service, $other, $engine->parse('2026-10-19T10:00:00+02:00')));
    }

    public function test_completed_and_no_show_visits_are_recorded_without_claiming_cash_revenue(): void
    {
        Salon::current()->update(['features' => ['financials' => true]]);
        $a = $this->create();
        $b = $this->create(['starts_at' => '2026-10-19T13:00:00+02:00']);
        $this->travelTo(CarbonImmutable::parse('2026-10-19T14:00:00+00:00'));
        app(BookingService::class)->change($a, ['action' => 'complete', 'revision' => 1, 'actual_amount' => 10500], $this->owner);
        app(BookingService::class)->change($b, ['action' => 'no_show', 'revision' => 1], $this->owner);
        $this->actingAs($this->owner)->get(route('admin.today', ['date' => '2026-10-19']))->assertOk()->assertSee('10 500')->assertSee('nem pénzforgalmi adat')->assertSee('Nem jelent meg');
    }

    public function test_financial_module_controls_actual_amount_recording_and_visibility(): void
    {
        $hidden = $this->create();
        $visible = $this->create(['starts_at' => '2026-10-19T13:00:00+02:00']);
        $this->travelTo(CarbonImmutable::parse('2026-10-19T14:00:00+00:00'));

        $this->actingAs($this->owner)->post(route('admin.booking.update', $hidden), ['action' => 'complete', 'revision' => 1, 'actual_amount' => 12345])->assertRedirect();
        $this->assertNull($hidden->fresh()->actual_amount);
        $this->get(route('admin.booking', $hidden))->assertOk()->assertDontSee('Rögzített szolgáltatási összeg')->assertDontSee('Foglaláskori ár');
        $this->get(route('admin.guest', $hidden->guest))->assertOk()->assertDontSee('9 500 Ft');

        Salon::current()->update(['features' => ['financials' => true]]);
        $this->post(route('admin.booking.update', $visible), ['action' => 'complete', 'revision' => 1, 'actual_amount' => 12000])->assertRedirect();
        $this->assertSame(12000, $visible->fresh()->actual_amount);
        $this->get(route('admin.booking', $visible))->assertOk()->assertSee('Rögzített szolgáltatási összeg')->assertSee('12 000')->assertSee('Foglaláskori ár');
    }

    public function test_anonymization_requires_password_and_no_future_bookings(): void
    {
        $booking = $this->create();
        $this->owner->update(['password' => 'long-test-password']);
        $this->actingAs($this->owner)->post(route('admin.anonymize', $booking->guest), ['password' => 'long-test-password', 'confirm' => 1])->assertSessionHasErrors('confirm');
        app(BookingService::class)->change($booking, ['action' => 'cancel', 'revision' => 1], $this->owner);
        $waiting = WaitlistEntry::factory()->create(['guest_id' => $booking->guest_id, 'service_id' => $this->service->id, 'staff_id' => $this->staff->id, 'note' => 'Személyes kérés']);
        $this->post(route('admin.anonymize', $booking->guest), ['password' => 'long-test-password', 'confirm' => 1])->assertRedirect();
        $this->assertNotNull($booking->guest->fresh()->anonymized_at);
        $this->assertSame('cancelled', $waiting->fresh()->status);
        $this->assertNull($waiting->fresh()->note);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_backup_is_encrypted_and_restores_into_empty_database_only(): void
    {
        $booking = $this->create();
        $connection = GoogleCalendarConnection::factory()->create(['staff_id' => $this->staff->id]);
        GoogleCalendarEvent::factory()->create(['google_calendar_connection_id' => $connection->id, 'booking_id' => $booking->id]);
        GoogleCalendarBusy::factory()->create(['google_calendar_connection_id' => $connection->id]);
        $plan = PassPlan::factory()->create(['service_id' => $this->service->id]);
        $pass = GuestPass::factory()->create(['guest_id' => $booking->guest_id, 'pass_plan_id' => $plan->id]);
        PassUse::factory()->create(['guest_pass_id' => $pass->id, 'booking_id' => $booking->id]);
        LoyaltyEntry::factory()->create(['booking_id' => $booking->id, 'guest_id' => $booking->guest_id]);
        WaitlistEntry::factory()->create(['guest_id' => $booking->guest_id, 'service_id' => $this->service->id, 'staff_id' => $this->staff->id]);
        SmsOutboxMessage::factory()->create(['booking_id' => $booking->id]);
        Storage::fake('public');
        Storage::disk('public')->put('gallery/test.png', 'sample-image-bytes');
        Storage::disk('public')->put('branding/hero.webp', 'hero-image-bytes');
        Salon::current()->update(['gallery' => ['gallery/test.png'], 'hero_image' => 'branding/hero.webp', 'appearance' => ['layout' => 'split']]);
        $path = storage_path('framework/testing-'.Str::uuid().'.backup');
        try {
            $this->artisan('salon:backup', ['--path' => $path])->assertSuccessful();
            $raw = file_get_contents($path);
            $this->assertStringNotContainsString('guest@example.test', $raw);
            $this->artisan('salon:restore', ['path' => $path])->assertFailed();
            $tables = BackupSalon::TABLES;
            foreach (array_reverse($tables) as $table) {
                DB::table($table)->delete();
            }
            Storage::disk('public')->delete('gallery/test.png');
            Storage::disk('public')->delete('branding/hero.webp');
            $this->artisan('salon:restore', ['path' => $path])->assertSuccessful();
            $restored = Booking::first();
            $this->assertSame($booking->manage_token, $restored->manage_token);
            $this->assertSame('guest@example.test', $restored->guest->email);
            $this->assertSame('sample-image-bytes', Storage::disk('public')->get('gallery/test.png'));
            $this->assertSame('hero-image-bytes', Storage::disk('public')->get('branding/hero.webp'));
            $this->assertSame('split', Salon::current()->appearance['layout']);
            $this->assertDatabaseCount('google_calendar_events', 1);
            $this->assertDatabaseCount('google_calendar_busies', 1);
            $this->assertSame('test-refresh-token', GoogleCalendarConnection::sole()->refresh_token);
            $this->assertDatabaseCount('guest_passes', 1);
            $this->assertDatabaseCount('pass_uses', 1);
            $this->assertDatabaseCount('loyalty_entries', 1);
            $this->assertDatabaseCount('waitlist_entries', 1);
            $this->assertDatabaseCount('sms_outbox_messages', 1);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_legacy_backup_restores_without_optional_module_tables(): void
    {
        $booking = $this->create();
        $path = storage_path('framework/testing-'.Str::uuid().'.backup');
        $legacyPath = storage_path('framework/testing-'.Str::uuid().'.backup');
        try {
            $this->artisan('salon:backup', ['--path' => $path])->assertSuccessful();
            $snapshot = json_decode(Crypt::decryptString(file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);
            $snapshot['format'] = 1;
            $snapshot['tables'] = array_intersect_key($snapshot['tables'], array_flip(BackupSalon::LEGACY_TABLES));
            unset($snapshot['tables']['salons'][0]['features'], $snapshot['tables']['services'][0]['loyalty_points'], $snapshot['tables']['bookings'][0]['sms_opt_in']);
            file_put_contents($legacyPath, Crypt::encryptString(json_encode($snapshot, JSON_THROW_ON_ERROR)));
            foreach (array_reverse(BackupSalon::TABLES) as $table) {
                DB::table($table)->delete();
            }

            $this->artisan('salon:restore', ['path' => $legacyPath])->assertSuccessful();
            $this->assertSame($booking->manage_token, Booking::sole()->manage_token);
            $this->assertDatabaseCount('guest_passes', 0);
            $this->assertDatabaseCount('sms_outbox_messages', 0);
        } finally {
            foreach ([$path, $legacyPath] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function test_owner_pages_render_and_login_is_rate_limited(): void
    {
        $this->actingAs($this->owner);
        foreach (['admin.today', 'admin.calendar', 'admin.create', 'admin.guests', 'admin.services', 'admin.staff', 'admin.settings', 'admin.notifications'] as $route) {
            $this->get(route($route))->assertOk();
        }
        auth()->logout();
        foreach (range(1, 5) as $i) {
            $this->post(route('login.submit'), ['email' => 'wrong@example.test', 'password' => 'wrong'])->assertSessionHasErrors();
        }
        $this->post(route('login.submit'), ['email' => 'wrong@example.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_public_forms_render_for_both_brand_themes_and_staff_links_select_eligible_services(): void
    {
        $this->get(route('booking.form'))->assertOk()->assertSee('Foglalás véglegesítése');
        $other = Service::factory()->create(['name' => 'Másik szolgáltatás']);
        $person = $other->staff()->first();
        $this->get(route('booking.form', ['staff_id' => $person->id]))->assertOk()->assertSee($other->name);
        Salon::current()->update(['theme' => 'barber', 'accent' => '#c2ae78']);
        $this->get(route('home'))->assertOk()->assertSee('barber');
        $this->get(route('booking.form'))->assertOk();
    }

    public function test_guest_contact_changes_are_explicit_and_do_not_merge_records(): void
    {
        $booking = $this->create();
        $another = Guest::factory()->create();
        $this->actingAs($this->owner)->post(route('admin.contact', $booking->guest), ['name' => $another->name, 'email' => $another->email, 'phone' => $another->phone])->assertRedirect();
        $this->assertDatabaseCount('guests', 2);
        $this->assertSame($another->email, $booking->guest->fresh()->email);
    }

    public function test_publication_requires_valid_capacity_and_uploads_reject_svg(): void
    {
        $this->actingAs($this->owner);
        $data = Salon::current()->only(['name', 'tagline', 'about', 'email', 'phone', 'address', 'theme', 'accent', 'font', 'terms', 'privacy', 'min_notice_minutes', 'horizon_days', 'cancel_notice_hours', 'reminder_hours', 'retention_days']);
        $data += ['hours' => array_fill(1, 7, ['start' => '09:00', 'end' => '18:00']), 'published' => 1, 'content_rights' => 1];
        $this->service->update(['active' => false]);
        Salon::current()->update(['published' => false]);
        $this->post(route('admin.settings.save'), $data)->assertSessionHasErrors('published');
        $this->assertFalse(Salon::current()->published);
        $this->service->update(['active' => true]);
        $this->post(route('admin.settings.save'), $data)->assertSessionHasNoErrors();
        $this->assertTrue(Salon::current()->published);
        $data['logo'] = UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->post(route('admin.settings.save'), $data)->assertSessionHasErrors('logo');
    }
}
