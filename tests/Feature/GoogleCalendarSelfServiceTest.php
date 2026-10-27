<?php

namespace Tests\Feature;

use App\Models\GoogleCalendarConnection;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use App\Services\GoogleCalendarClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_account_redirect_is_allowed_only_on_calendar_management_page(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $calendar = $this->actingAs($owner)->get(route('admin.google.index'));
        $this->assertStringContainsString("form-action 'self' https://accounts.google.com;", $calendar->headers->get('Content-Security-Policy'));
        $dashboard = $this->get(route('admin.today'));
        $this->assertStringContainsString("form-action 'self';", $dashboard->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('https://accounts.google.com', $dashboard->headers->get('Content-Security-Policy'));
    }

    public function test_staff_can_manage_only_their_own_calendar(): void
    {
        Salon::factory()->create();
        $staff = Staff::factory()->create(['name' => 'Own Specialist']);
        $other = Staff::factory()->create(['name' => 'Other Specialist']);
        $otherConnection = GoogleCalendarConnection::factory()->create(['staff_id' => $other->id]);
        $user = User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id]);
        config(['services.google_calendar.client_id' => 'client', 'services.google_calendar.client_secret' => 'secret', 'services.google_calendar.redirect_uri' => 'https://client.example.test/admin/google-naptar/visszateres']);
        $this->actingAs($user)->get(route('admin.google.index'))->assertOk()->assertSee('Own Specialist')->assertDontSee('Other Specialist');
        $this->post(route('admin.google.connect', $other))->assertForbidden();
        $this->post(route('admin.google.sync', $otherConnection))->assertForbidden();
        $this->post(route('admin.google.disconnect', $otherConnection), ['confirm' => 1])->assertForbidden();
        $this->post(route('admin.google.connect', $staff))->assertRedirectContains('accounts.google.com');
    }

    public function test_common_redirect_without_relay_configuration_does_not_start_oauth(): void
    {
        Salon::factory()->create();
        $staff = Staff::factory()->create();
        $user = User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id]);
        config(['services.google_calendar.client_id' => 'client', 'services.google_calendar.client_secret' => 'secret', 'services.google_calendar.redirect_uri' => 'https://szalon.pro/google-naptar/kozponti-visszateres']);
        $this->actingAs($user)->post(route('admin.google.connect', $staff))->assertSessionHasErrors('google');
    }

    public function test_common_relay_delivers_only_signed_registered_unexpired_state_and_callback_consumes_it_once(): void
    {
        Salon::factory()->create();
        $staff = Staff::factory()->create();
        $user = User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id]);
        $callback = 'https://client.example.test/admin/google-naptar/visszateres';
        $key = str_repeat('x', 40);
        config(['services.google_calendar.client_id' => 'client', 'services.google_calendar.client_secret' => 'secret',
            'services.google_calendar.redirect_uri' => 'https://szalon.pro/google-naptar/kozponti-visszateres',
            'services.google_calendar.relay_callback' => $callback, 'services.google_calendar.relay_key' => $key,
            'services.google_calendar.relay_clients' => json_encode([$callback => $key])]);
        $location = $this->actingAs($user)->post(route('admin.google.connect', $staff))->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY), $parameters);
        $state = $parameters['state'];
        $this->get(route('google.relay', ['state' => $state.'x', 'code' => 'test']))->assertBadRequest();
        $this->get(route('google.relay', ['state' => $state, 'code' => 'test']))->assertRedirectContains($callback.'?')->assertHeader('Cache-Control', 'no-store, private');
        config(['services.google_calendar.relay_clients' => '{}']);
        $this->get(route('google.relay', ['state' => $state, 'code' => 'test']))->assertBadRequest();
        config(['services.google_calendar.relay_clients' => json_encode([$callback => $key])]);
        Http::fake(fn (Request $request) => str_contains($request->url(), 'oauth2.googleapis.com/token')
            ? Http::response(['access_token' => 'access', 'refresh_token' => 'refresh', 'expires_in' => 3600, 'scope' => GoogleCalendarClient::Scope.' '.GoogleCalendarClient::CalendarIdentityScope])
            : (str_contains($request->url(), 'calendarList/primary') ? Http::response(['id' => 'calendar@example.test', 'timeZone' => 'Europe/Budapest']) : Http::response(['items' => []])));
        $this->get(route('admin.google.callback', ['state' => $state, 'code' => 'test']))->assertRedirect(route('admin.google.index'));
        $this->assertDatabaseHas('google_calendar_connections', ['staff_id' => $staff->id, 'sync_status' => 'connected']);
        $this->get(route('admin.google.callback', ['state' => $state, 'code' => 'test']))->assertSessionHasErrors('google');
        $this->travel(11)->minutes();
        $this->get(route('google.relay', ['state' => $state, 'code' => 'test']))->assertBadRequest();
    }
}
