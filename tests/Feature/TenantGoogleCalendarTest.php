<?php

namespace Tests\Feature;

use App\Models\GoogleCalendarBusy;
use App\Models\GoogleCalendarConnection;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use App\Services\GoogleCalendarClient;
use App\Services\GoogleCalendarRelay;
use App\Services\GoogleCalendarSync;
use App\Services\TenantContext;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TenantGoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_oauth_round_trip_uses_central_callback_and_saves_only_its_calendar(): void
    {
        Salon::factory()->create();
        $tenant = app(TenantProvisioner::class)->create('judit', 'Judit Fodrászat', 'owner@example.test');
        [$staff, $owner] = app(TenantContext::class)->run($tenant, fn (): array => [Staff::factory()->create(), User::sole()]);
        config(['services.google_calendar.client_id' => 'test', 'services.google_calendar.client_secret' => 'test', 'services.google_calendar.redirect_uri' => 'https://central.test/admin/google-naptar/visszateres']);
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access', 'refresh_token' => 'test-refresh', 'expires_in' => 3600, 'scope' => GoogleCalendarClient::Scope.' '.GoogleCalendarClient::CalendarIdentityScope]),
            'www.googleapis.com/calendar/v3/users/me/calendarList/primary*' => Http::response(['id' => 'judit@example.test', 'timeZone' => 'Europe/Budapest']),
            'www.googleapis.com/calendar/v3/calendars/*/events*' => Http::response(['items' => []]),
        ]);

        $start = $this->actingAs($owner)->post($tenant->url().'/admin/google-naptar/'.$staff->id.'/kapcsolodas')->assertRedirect();
        parse_str(parse_url($start->headers->get('Location'), PHP_URL_QUERY), $query);
        $centralCallback = rtrim(config('app.url'), '/').'/google-naptar/kozponti-visszateres';
        $this->assertSame($centralCallback, $query['redirect_uri']);
        $tenantPendingOAuth = session()->get('google_calendar_oauth');
        session()->flush();
        auth()->forgetGuards();
        $relay = $this->get($centralCallback.'?'.http_build_query(['state' => $query['state'], 'code' => 'test-code']))->assertRedirect();
        $target = $relay->headers->get('Location');
        $this->assertStringStartsWith($tenant->url().'/admin/google-naptar/visszateres?', $target);
        $this->actingAs($owner)->withSession(['google_calendar_oauth' => $tenantPendingOAuth])->get($target)->assertRedirect($tenant->url().'/admin/google-naptar')->assertSessionHasNoErrors();
        app(TenantContext::class)->run($tenant, function () use ($staff): void {
            $connection = GoogleCalendarConnection::sole();
            $this->assertSame($staff->id, $connection->staff_id);
            $this->assertSame('connected', $connection->sync_status);
            $this->assertSame('judit@example.test', $connection->calendar_id);
        });
        $this->assertSame(0, GoogleCalendarConnection::count());
        Http::assertSent(fn ($request): bool => $request->url() === 'https://oauth2.googleapis.com/token' && $request['redirect_uri'] === $centralCallback);
        $this->get($target)->assertRedirect()->assertSessionHasErrors('google');
    }

    public function test_imported_google_busy_events_stay_with_their_customer(): void
    {
        Salon::factory()->create();
        $tenant = app(TenantProvisioner::class)->create('calendar', 'Calendar salon', 'owner@example.test');
        config(['services.google_calendar.client_id' => 'test', 'services.google_calendar.client_secret' => 'test', 'services.google_calendar.redirect_uri' => 'https://central.test/google-naptar/kozponti-visszateres']);
        Http::fake(['www.googleapis.com/*' => Http::response(['items' => [['id' => 'external', 'start' => ['dateTime' => now()->addDay()->toIso8601String()], 'end' => ['dateTime' => now()->addDay()->addHour()->toIso8601String()]]]])]);
        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            $connection = GoogleCalendarConnection::factory()->create();
            app(GoogleCalendarSync::class)->sync($connection);
            $this->assertSame($tenant->id, GoogleCalendarBusy::sole()->tenant_id);
        });
        $this->assertSame(0, GoogleCalendarBusy::count());
    }

    public function test_central_google_relay_accepts_only_signed_active_customer_callbacks(): void
    {
        Salon::factory()->create();
        $tenant = app(TenantProvisioner::class)->create('calendar', 'Calendar salon', 'owner@example.test');
        $relay = app(GoogleCalendarRelay::class);
        $state = app(TenantContext::class)->run($tenant, fn (): string => $relay->state(str_repeat('a', 64)));
        $this->assertSame($tenant->url().'/admin/google-naptar/visszateres', $relay->callback($state));
        $this->assertNull($relay->callback($state.'x'));
        $tenant->update(['status' => 'suspended']);
        $this->assertNull($relay->callback($state));
    }
}
