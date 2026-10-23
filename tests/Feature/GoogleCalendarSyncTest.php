<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\GoogleCalendarBusy;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEvent;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\Availability;
use App\Services\BookingService;
use App\Services\GoogleCalendarClient;
use App\Services\GoogleCalendarSync;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoogleCalendarSyncTest extends TestCase
{
    use RefreshDatabase;

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
        $this->owner = User::factory()->create(['role' => 'owner']);
        config()->set('services.google_calendar', ['client_id' => 'test-client-id', 'client_secret' => 'test-client-secret', 'redirect_uri' => route('admin.google.callback')]);
    }

    public function test_owner_connects_with_state_and_tokens_are_encrypted(): void
    {
        $staffUser = User::factory()->create(['role' => 'staff', 'staff_id' => $this->staff->id]);
        $this->actingAs($staffUser)->get(route('admin.google.index'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('admin.google.index'))->assertOk()->assertSee('Google Naptár');
        $redirect = $this->actingAs($this->owner)->post(route('admin.google.connect', $this->staff))->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $redirect);
        parse_str(parse_url($redirect, PHP_URL_QUERY), $parameters);
        $this->assertSame('offline', $parameters['access_type']);
        $this->assertContains(GoogleCalendarClient::Scope, explode(' ', $parameters['scope']));

        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'secret-access', 'refresh_token' => 'secret-refresh', 'expires_in' => 3600,
                    'scope' => GoogleCalendarClient::Scope.' '.GoogleCalendarClient::CalendarIdentityScope], 200);
            }
            if (str_contains($request->url(), '/calendarList/primary')) {
                return Http::response(['id' => 'owner-calendar@example.test', 'timeZone' => 'Europe/Budapest'], 200);
            }

            return Http::response(['items' => []], 200);
        });
        $this->get(route('admin.google.callback', ['state' => $parameters['state'], 'code' => 'valid-code']))->assertRedirect(route('admin.google.index'));
        $connection = GoogleCalendarConnection::firstOrFail();
        $this->assertSame('owner-calendar@example.test', $connection->calendar_id);
        $this->assertSame('connected', $connection->sync_status);
        $this->assertNotSame('secret-refresh', DB::table('google_calendar_connections')->value('refresh_token'));
        $this->assertSame('secret-refresh', $connection->refresh_token);
    }

    public function test_invalid_oauth_state_cannot_create_connection(): void
    {
        $this->actingAs($this->owner)->post(route('admin.google.connect', $this->staff));
        $this->get(route('admin.google.callback', ['state' => 'wrong', 'code' => 'code']))->assertSessionHasErrors('google');
        $this->assertDatabaseCount('google_calendar_connections', 0);
    }

    public function test_same_google_calendar_cannot_be_connected_to_two_staff_members(): void
    {
        GoogleCalendarConnection::factory()->create(['staff_id' => $this->staff->id, 'calendar_id' => 'shared@example.test']);
        $otherStaff = Staff::factory()->create();
        $redirect = $this->actingAs($this->owner)->post(route('admin.google.connect', $otherStaff))->headers->get('Location');
        parse_str(parse_url($redirect, PHP_URL_QUERY), $parameters);
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'other-access', 'refresh_token' => 'other-refresh', 'expires_in' => 3600,
                    'scope' => GoogleCalendarClient::Scope.' '.GoogleCalendarClient::CalendarIdentityScope], 200);
            }

            return Http::response(['id' => 'shared@example.test', 'timeZone' => 'Europe/Budapest'], 200);
        });

        $this->get(route('admin.google.callback', ['state' => $parameters['state'], 'code' => 'valid-code']))->assertSessionHasErrors('google');
        $this->assertDatabaseCount('google_calendar_connections', 1);
        $this->assertDatabaseMissing('google_calendar_connections', ['staff_id' => $otherStaff->id]);
    }

    public function test_external_events_block_slots_without_importing_event_details(): void
    {
        $connection = GoogleCalendarConnection::factory()->create(['staff_id' => $this->staff->id]);
        Http::fake(['www.googleapis.com/calendar/v3/*' => Http::response(['items' => [[
            'id' => 'private-event-1', 'summary' => 'Személyes találkozó', 'start' => ['dateTime' => '2026-10-19T10:00:00+02:00'],
            'end' => ['dateTime' => '2026-10-19T11:00:00+02:00'], 'transparency' => 'opaque',
        ]]], 200)]);

        app(GoogleCalendarSync::class)->sync($connection);
        $this->assertDatabaseCount('google_calendar_busies', 1);
        $this->assertSame(hash('sha256', 'private-event-1'), GoogleCalendarBusy::first()->event_key);
        $this->assertFalse(app(Availability::class)->canBook($this->service, $this->staff->fresh(), CarbonImmutable::parse('2026-10-19T10:00:00+02:00')));
        $this->assertTrue(app(Availability::class)->canBook($this->service, $this->staff->fresh(), CarbonImmutable::parse('2026-10-19T11:00:00+02:00')));
        $this->assertNotContains('summary', DB::getSchemaBuilder()->getColumnListing('google_calendar_busies'));
    }

    public function test_booking_export_is_idempotent_and_cancellation_removes_its_event(): void
    {
        $booking = $this->createBooking();
        $connection = GoogleCalendarConnection::factory()->create(['staff_id' => $this->staff->id]);
        $eventId = app(GoogleCalendarClient::class)->eventId($connection, $booking);
        $writes = [];
        $remoteEvent = null;
        Http::fake(function (Request $request) use (&$writes, &$remoteEvent) {
            if ($request->method() !== 'GET') {
                $writes[] = [$request->method(), $request->data()];
            }
            if ($request->method() === 'GET') {
                return Http::response(['items' => $remoteEvent ? [$remoteEvent] : []], 200);
            }
            $remoteEvent = $request->method() === 'DELETE' ? null : $request->data();

            return Http::response([], $request->method() === 'DELETE' ? 204 : 200);
        });
        app(GoogleCalendarSync::class)->sync($connection);
        app(GoogleCalendarSync::class)->sync($connection->fresh());
        $this->assertCount(1, array_filter($writes, fn (array $write) => $write[0] === 'POST'));
        $this->assertSame(1, GoogleCalendarEvent::firstOrFail()->synced_revision);
        $this->assertStringNotContainsString($booking->guest->name, json_encode($writes[0][1]));
        $this->assertStringNotContainsString($booking->guest->email, json_encode($writes[0][1]));
        $this->assertStringContainsString('2026. 10. 19. 10:00–11:00', $writes[0][1]['description']);
        $this->assertDatabaseCount('google_calendar_busies', 0);

        $booking = app(BookingService::class)->change($booking, ['action' => 'move', 'revision' => $booking->revision,
            'staff_id' => $this->staff->id, 'starts_at' => '2026-10-19T12:00:00+02:00'], $this->owner);
        app(GoogleCalendarSync::class)->sync($connection->fresh());
        $this->assertCount(1, array_filter($writes, fn (array $write) => $write[0] === 'PUT'));
        $this->assertSame(2, GoogleCalendarEvent::firstOrFail()->synced_revision);

        app(BookingService::class)->change($booking, ['action' => 'cancel', 'revision' => $booking->revision], $this->owner);
        app(GoogleCalendarSync::class)->sync($connection->fresh());
        $this->assertCount(1, array_filter($writes, fn (array $write) => $write[0] === 'DELETE'));
        $this->assertDatabaseCount('google_calendar_events', 0);
    }

    public function test_changed_or_deleted_google_booking_is_restored_from_local_booking(): void
    {
        $booking = $this->createBooking();
        $connection = GoogleCalendarConnection::factory()->create(['staff_id' => $this->staff->id]);
        $remoteEvent = null;
        $methods = [];
        Http::fake(function (Request $request) use (&$remoteEvent, &$methods) {
            $method = $request->method();
            if ($method === 'GET' && str_contains($request->url(), '/events/')) {
                return Http::response([], 404);
            }
            if ($method === 'GET') {
                return Http::response(['items' => $remoteEvent ? [$remoteEvent] : []], 200);
            }
            $methods[] = $method;
            if ($method === 'PUT' && $remoteEvent === null) {
                return Http::response([], 404);
            }
            $remoteEvent = $request->data();

            return Http::response([], 200);
        });

        app(GoogleCalendarSync::class)->sync($connection);
        $this->assertSame(['POST'], $methods);
        $remoteEvent['start']['dateTime'] = CarbonImmutable::parse($booking->busy_from)->addHour()->toRfc3339String();
        app(GoogleCalendarSync::class)->sync($connection->fresh());
        $this->assertSame(['POST', 'PUT'], $methods);
        $this->assertSame($booking->busy_from->toRfc3339String(), $remoteEvent['start']['dateTime']);

        $remoteEvent = null;
        app(GoogleCalendarSync::class)->sync($connection->fresh());
        $this->assertSame(['POST', 'PUT', 'PUT', 'POST'], $methods);
        $this->assertSame($booking->busy_from->toRfc3339String(), $remoteEvent['start']['dateTime']);
    }

    public function test_failed_or_stale_import_stops_new_booking_until_sync_recovers(): void
    {
        $connection = GoogleCalendarConnection::factory()->create(['staff_id' => $this->staff->id, 'last_imported_at' => now()->subMinutes(16)]);
        $start = CarbonImmutable::parse('2026-10-19T10:00:00+02:00');
        $this->assertFalse(app(Availability::class)->canBook($this->service, $this->staff->fresh(), $start));
        Http::fake(['www.googleapis.com/calendar/v3/*' => Http::response(['error' => ['code' => 503]], 503)]);
        try {
            app(GoogleCalendarSync::class)->sync($connection);
            $this->fail('A sikertelen Google-válasznak hibát kell okoznia.');
        } catch (\RuntimeException) {
            $this->assertSame('error', $connection->fresh()->sync_status);
            $this->assertSame('google_http_503', $connection->fresh()->last_error_code);
        }
    }

    public function test_disconnect_deletes_only_exported_events_and_requires_confirmation(): void
    {
        $booking = $this->createBooking();
        $connection = GoogleCalendarConnection::factory()->create(['staff_id' => $this->staff->id]);
        GoogleCalendarEvent::factory()->create(['google_calendar_connection_id' => $connection->id, 'booking_id' => $booking->id]);
        $this->actingAs($this->owner)->post(route('admin.google.disconnect', $connection))->assertSessionHasErrors('confirm');
        Http::fake(['www.googleapis.com/calendar/v3/*' => Http::response([], 204)]);
        $this->post(route('admin.google.disconnect', $connection), ['confirm' => 1])->assertRedirect();
        $this->assertDatabaseCount('google_calendar_connections', 0);
        $this->assertDatabaseCount('google_calendar_events', 0);
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE');
    }

    public function test_refresh_token_revocation_requires_reauthorization_and_blocks_booking(): void
    {
        $connection = GoogleCalendarConnection::factory()->create(['staff_id' => $this->staff->id, 'token_expires_at' => now()->subMinute()]);
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);
        try {
            app(GoogleCalendarSync::class)->sync($connection);
            $this->fail('A visszavont frissítőtokennek hibát kell okoznia.');
        } catch (\RuntimeException) {
            $this->assertSame('reconnect', $connection->fresh()->sync_status);
            $this->assertFalse(app(Availability::class)->canBook($this->service, $this->staff->fresh(), CarbonImmutable::parse('2026-10-19T10:00:00+02:00')));
        }
    }

    public function test_early_unauthorized_access_token_is_refreshed_and_request_retried_once(): void
    {
        $connection = GoogleCalendarConnection::factory()->create(['staff_id' => $this->staff->id]);
        $eventRequests = 0;
        $refreshRequests = 0;
        Http::fake(function (Request $request) use (&$eventRequests, &$refreshRequests) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                $refreshRequests++;

                return Http::response(['access_token' => 'fresh-access-token', 'expires_in' => 3600], 200);
            }
            $eventRequests++;

            return $eventRequests === 1
                ? Http::response(['error' => ['code' => 401]], 401)
                : Http::response(['items' => []], 200);
        });

        app(GoogleCalendarSync::class)->sync($connection);
        $this->assertSame(2, $eventRequests);
        $this->assertSame(1, $refreshRequests);
        $this->assertSame('fresh-access-token', $connection->fresh()->access_token);
        $this->assertSame('connected', $connection->fresh()->sync_status);
    }

    public function test_temporary_calendar_errors_are_retried_before_blocking_bookings(): void
    {
        $connection = GoogleCalendarConnection::factory()->create(['staff_id' => $this->staff->id]);
        $requests = 0;
        Http::fake(function () use (&$requests) {
            $requests++;

            return match ($requests) {
                1 => Http::response(['error' => ['code' => 429]], 429),
                2 => Http::response(['error' => ['code' => 503]], 503),
                default => Http::response(['items' => []], 200),
            };
        });

        app(GoogleCalendarSync::class)->sync($connection);
        $this->assertSame(3, $requests);
        $this->assertSame('connected', $connection->fresh()->sync_status);
    }

    public function test_only_rate_limit_403_errors_are_retried(): void
    {
        $connection = GoogleCalendarConnection::factory()->create(['staff_id' => $this->staff->id]);
        $requests = 0;
        Http::fake(function () use (&$requests) {
            $requests++;

            return $requests === 1
                ? Http::response(['error' => ['errors' => [['reason' => 'userRateLimitExceeded']]]], 403)
                : Http::response(['items' => []], 200);
        });
        app(GoogleCalendarSync::class)->sync($connection);
        $this->assertSame(2, $requests);
    }

    public function test_non_rate_limit_403_is_not_retried(): void
    {
        $connection = GoogleCalendarConnection::factory()->create(['staff_id' => $this->staff->id]);
        $requests = 0;
        Http::fake(function () use (&$requests) {
            $requests++;

            return Http::response(['error' => ['errors' => [['reason' => 'forbidden']]]], 403);
        });
        try {
            app(GoogleCalendarSync::class)->sync($connection->fresh());
            $this->fail('Az általános tiltás nem próbálható újra.');
        } catch (\RuntimeException) {
            $this->assertSame(1, $requests);
            $this->assertSame('error', $connection->fresh()->sync_status);
        }
    }

    private function createBooking(): Booking
    {
        return app(BookingService::class)->create([
            'service_id' => $this->service->id,
            'staff_id' => $this->staff->id,
            'starts_at' => '2026-10-19T10:00:00+02:00',
            'name' => 'Titkos Vendég',
            'email' => 'secret@example.test',
            'phone' => null,
            'request_key' => Str::uuid()->toString(),
        ]);
    }
}
