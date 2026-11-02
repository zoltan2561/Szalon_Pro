<?php

namespace Tests\Feature;

use App\Models\GoogleCalendarBusy;
use App\Models\GoogleCalendarConnection;
use App\Models\Salon;
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
