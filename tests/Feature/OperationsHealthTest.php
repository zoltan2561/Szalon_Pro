<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\OutboxMessage;
use App\Models\Salon;
use App\Models\User;
use App\Services\OperationsHealth;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationsHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Salon::factory()->create();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test']);
        app(OperationsHealth::class)->recordRun('email');
        app(OperationsHealth::class)->recordRun('sms');
    }

    public function test_doctor_succeeds_only_with_current_schema_heartbeat_and_configuration(): void
    {
        $this->artisan('salon:doctor')->assertSuccessful();
        $this->travel(6)->minutes();
        $this->artisan('salon:doctor')->expectsOutputToContain('heartbeat_email')->assertFailed();
    }

    public function test_doctor_reports_due_messages_and_enabled_but_unconfigured_sms(): void
    {
        Salon::current()->update(['features' => ['sms' => true]]);
        $booking = Booking::factory()->create();
        OutboxMessage::create(['booking_id' => $booking->id, 'revision' => 1, 'kind' => 'confirmation', 'scheduled_at' => now()]);
        $this->artisan('salon:doctor')->expectsOutputToContain('due_email')->expectsOutputToContain('sms:')->assertFailed();
    }

    public function test_disabled_sms_does_not_require_its_own_heartbeat(): void
    {
        $health = app(OperationsHealth::class);
        Cache::forget($health->heartbeatKey('sms'));
        $this->artisan('salon:doctor')->assertSuccessful();
        $this->assertArrayNotHasKey('heartbeat_sms', $health->warnings());
        Salon::current()->update(['features' => ['sms' => true]]);
        $this->assertArrayHasKey('heartbeat_sms', $health->warnings());
        $this->artisan('salon:doctor')->expectsOutputToContain('heartbeat_sms')->assertFailed();
    }

    public function test_missing_migration_or_table_has_controlled_admin_response_instead_of_exception(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        DB::table('migrations')->where('migration', '2026_10_23_064701_create_sms_outbox_messages_table')->delete();
        $this->artisan('salon:doctor')->expectsOutputToContain('schema:')->assertFailed();
        foreach (['admin.today', 'admin.guests', 'admin.notifications'] as $route) {
            $this->actingAs($owner)->get(route($route))->assertStatus(503)->assertSee('Rendszerfrissítés szükséges');
        }
        Schema::drop('sms_outbox_messages');
        $this->artisan('salon:doctor')->assertFailed();
        $this->get(route('admin.today'))->assertStatus(503);
    }

    public function test_owner_warning_discloses_no_configuration_secrets(): void
    {
        config(['mail.default' => 'array', 'services.sms.auth_token' => 'must-not-render']);
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get(route('admin.today'))->assertOk()->assertSee('Működési figyelmeztetések')->assertDontSee('must-not-render');
    }

    public function test_notification_commands_record_separate_runs_and_page_shows_local_time_and_due_counts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25T07:00:00+00:00'));
        $this->artisan('salon:notify')->assertSuccessful();
        $this->artisan('salon:sms-notify')->assertSuccessful();
        $health = app(OperationsHealth::class);
        $this->assertSame(now()->timestamp, $health->lastRun('email')->timestamp);
        $this->assertSame(now()->timestamp, $health->lastRun('sms')->timestamp);
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get(route('admin.notifications'))->assertOk()->assertSee('2026.10.25. 08:00')->assertSee('Esedékes sor: 0');
    }
}
