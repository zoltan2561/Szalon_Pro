<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\TimeBlock;
use App\Models\User;
use App\Services\OperationsHealth;
use App\Services\SendPasswordResetLink;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicDemoViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_click_entry_authenticates_only_the_provisioned_viewer(): void
    {
        Salon::factory()->create(['demo' => true]);
        config()->set('salon.demo_viewer_enabled', true);
        $viewer = $this->viewer();
        $this->post('/demo-belepes')->assertRedirect(route('admin.today'));
        $this->assertAuthenticatedAs($viewer);
        $this->assertNotEmpty(session('password_hash_web'));
        $this->get(route('admin.today'))->assertOk();
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_demo_entry_is_disabled_by_default_and_never_enabled_on_customer_or_unpublished_sites(): void
    {
        $salon = Salon::factory()->create(['demo' => true]);
        $this->viewer();
        config()->set('salon.demo_viewer_enabled', false);
        $this->post('/demo-belepes')->assertNotFound();
        config()->set('salon.demo_viewer_enabled', true);
        $salon->update(['demo' => false]);
        $this->post('/demo-belepes')->assertNotFound();
        $salon->update(['demo' => true, 'published' => false]);
        $this->post('/demo-belepes')->assertNotFound();
        $this->assertGuest();
    }

    public function test_one_click_entry_requires_csrf_and_is_rate_limited(): void
    {
        Salon::factory()->create(['demo' => true]);
        config()->set('salon.demo_viewer_enabled', true);
        $this->viewer();
        $this->app['env'] = 'production';
        $this->post('/demo-belepes')->assertStatus(419);
        $this->app['env'] = 'testing';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/demo-belepes')->assertRedirect(route('admin.today'));
            $this->post(route('logout'))->assertRedirect(route('home'));
        }
        $this->post('/demo-belepes')->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_an_existing_real_owner_cannot_become_a_public_demo_login(): void
    {
        Salon::factory()->create(['demo' => true]);
        config()->set('salon.demo_viewer_enabled', true);
        User::factory()->create(['role' => 'owner', 'email' => 'demo@example.test']);
        $this->post('/demo-belepes')->assertNotFound();
        $this->assertGuest();
        $this->artisan('salon:demo-user')->assertExitCode(Command::FAILURE);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['email' => 'demo@example.test', 'is_demo_viewer' => false]);
    }

    public function test_demo_entry_is_unavailable_before_the_viewer_migration_is_applied(): void
    {
        Salon::factory()->create(['demo' => true]);
        config()->set('salon.demo_viewer_enabled', true);
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_demo_viewer');
        });
        $this->post('/demo-belepes')->assertNotFound();
        $this->get(route('home'))->assertOk();
        $this->assertGuest();
    }

    public function test_provisioning_refuses_customer_and_unpublished_installations(): void
    {
        $salon = Salon::factory()->create(['demo' => false]);
        $this->artisan('salon:demo-user')->assertExitCode(Command::FAILURE);
        $salon->update(['demo' => true, 'published' => false]);
        $this->artisan('salon:demo-user')->assertExitCode(Command::FAILURE);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_provisioning_is_idempotent_and_preserves_real_owner_accounts(): void
    {
        Salon::factory()->create(['demo' => true]);
        config()->set('salon.demo_viewer_enabled', false);
        $owner = User::factory()->create(['role' => 'owner']);
        $original = $owner->fresh()->getRawOriginal();
        $this->artisan('salon:demo-user')->assertSuccessful();
        $viewer = User::where('email', 'demo@example.test')->firstOrFail();
        $password = $viewer->getAuthPassword();
        $this->assertTrue($viewer->isDemoViewer());
        $this->assertSame('owner', $viewer->role);
        $this->assertFalse($viewer->can_manage_bookings);
        $this->assertFalse($viewer->canViewGuestNotes());
        $this->assertNotEmpty($password);
        $this->artisan('salon:demo-user')->assertSuccessful();
        $this->assertSame($password, $viewer->fresh()->getAuthPassword());
        $this->assertSame($original, $owner->fresh()->getRawOriginal());
        $this->assertDatabaseCount('users', 2);
    }

    #[DataProvider('unsafeRequests')]
    public function test_viewer_cannot_mutate_admin_or_public_endpoints(string $method, string $path): void
    {
        Salon::factory()->create(['demo' => true]);
        config()->set('salon.demo_viewer_enabled', true);
        $booking = Booking::factory()->create();
        $before = $booking->fresh()->getRawOriginal();
        $this->actingAs($this->viewer())->call($method, $path, ['action' => 'cancel', 'revision' => 0, 'name' => 'Changed'])->assertForbidden();
        $this->assertSame($before, $booking->fresh()->getRawOriginal());
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('guests', 1);
        $this->assertDatabaseCount('users', 1);
    }

    public static function unsafeRequests(): array
    {
        return [
            ['POST', '/admin/foglalas/1'], ['POST', '/admin/foglalas'],
            ['POST', '/admin/vendegek/1/megjegyzes'], ['POST', '/admin/vendegek/1/adatok'],
            ['POST', '/admin/vendegek/1/torles'], ['POST', '/admin/beallitasok'],
            ['POST', '/admin/fiok/jelszo'], ['POST', '/admin/foglalas/1/munkalap'],
            ['POST', '/admin/foglalas/1/atszervezes'], ['POST', '/admin/google-naptar/1/kapcsolodas'],
            ['DELETE', '/admin/szolgaltatasok/1'], ['DELETE', '/admin/munkatarsak/1'],
            ['POST', '/foglalas'], ['POST', '/varolista'],
            ['POST', '/kezeles/private-token'], ['POST', '/szabad-idopont/private-token'],
            ['POST', '/belepes'], ['POST', '/elfelejtett-jelszo'], ['POST', '/uj-jelszo'],
        ];
    }

    #[DataProvider('sensitivePages')]
    public function test_viewer_cannot_read_settings_credentials_exports_or_token_routes(string $path): void
    {
        Salon::factory()->create(['demo' => true]);
        config()->set('salon.demo_viewer_enabled', true);
        Booking::factory()->create();
        $this->actingAs($this->viewer())->get($path)->assertForbidden();
    }

    public static function sensitivePages(): array
    {
        return array_map(fn (string $path): array => [$path], [
            '/admin/beallitasok', '/admin/fiok', '/admin/google-naptar',
            '/admin/google-naptar/visszateres', '/admin/ertesitesek', '/admin/bizonylatok',
            '/admin/vendegek/1/export', '/admin/foglalas/1/foto/0', '/admin/foglalas/1/naptar',
            '/admin/megosztas', '/admin/modulok', '/kezeles/private-token',
            '/google-naptar/kozponti-visszateres', '/admin/varolista', '/elfelejtett-jelszo',
        ]);
    }

    public function test_allowed_demo_pages_never_expose_private_guest_data_notes_or_tokens(): void
    {
        Salon::factory()->create(['demo' => true, 'features' => ['statistics' => true]]);
        config()->set('salon.demo_viewer_enabled', true);
        $guest = Guest::factory()->create(['name' => 'PrivateUniqueGuest', 'email' => 'private-unique@real.example', 'phone' => '+36309988776', 'notes' => 'PrivateUniqueGuestNotes']);
        $booking = Booking::factory()->create(['guest_id' => $guest->id, 'work_notes' => ['formula' => 'PrivateUniqueWorkNotes'], 'work_photos' => ['private-photo.jpg'], 'manage_token' => 'private-management-token']);
        Booking::factory()->create(['guest_id' => $guest->id, 'status' => 'completed', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHour(), 'work_notes' => ['formula' => 'PrivateUniquePreviousNotes']]);
        $this->actingAs($this->viewer());
        foreach ([route('admin.today'), route('admin.calendar'), route('admin.bookings.index'), route('admin.booking', $booking), route('admin.guests'), route('admin.guest', $guest), route('admin.create', ['guest_id' => $guest->id]), route('admin.services'), route('admin.staff'), route('admin.statistics.index')] as $url) {
            $response = $this->get($url)->assertOk();
            foreach (['PrivateUniqueGuest', 'private-unique@real.example', '+36309988776', '36309988776', 'PrivateUniqueGuestNotes', 'PrivateUniqueWorkNotes', 'PrivateUniquePreviousNotes', 'private-photo.jpg', 'private-management-token'] as $private) {
                $response->assertDontSee($private, false);
            }
        }
        $this->assertDatabaseHas('guests', ['id' => $guest->id, 'name' => 'PrivateUniqueGuest', 'email' => 'private-unique@real.example', 'phone' => '+36309988776']);
    }

    public function test_a_viewer_session_is_revoked_if_demo_configuration_is_disabled(): void
    {
        $salon = Salon::factory()->create(['demo' => true]);
        config()->set('salon.demo_viewer_enabled', true);
        $this->actingAs($this->viewer());
        $salon->update(['demo' => false]);
        $this->get(route('admin.today'))->assertNotFound();
        $this->assertGuest();
    }

    #[DataProvider('blockReasonPages')]
    public function test_private_time_block_reasons_are_hidden_from_demo_but_remain_visible_to_real_owners(string $routeName, string $view): void
    {
        Salon::factory()->create(['demo' => true]);
        config()->set('salon.demo_viewer_enabled', true);
        $startsAt = now('Europe/Budapest')->startOfWeek()->addDay()->setTime(10, 0)->toImmutable();
        $privateReason = 'PrivateCustomerName doctor appointment +36301112233';
        $block = TimeBlock::factory()->create(['starts_at' => $startsAt->utc(), 'ends_at' => $startsAt->addHour()->utc(), 'reason' => $privateReason]);
        $url = route($routeName, ['date' => $startsAt->toDateString(), 'view' => $view]);

        $response = $this->actingAs($this->viewer())->get($url)->assertOk();
        if ($view === 'week') {
            $response->assertSee('class="calendar-week-mobile"', false)
                ->assertSee('class="calendar-week-grid calendar-week-grid-timed"', false);
        }
        $this->assertSame(0, substr_count($response->getContent(), $privateReason), 'Private block reasons must not appear in demo HTML, including hidden weekly desktop and mobile panels.');
        $response->assertSee('Bemutató blokkolás');
        $this->assertDatabaseHas('time_blocks', ['id' => $block->id, 'reason' => $privateReason]);

        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get($url)->assertOk()->assertSee($privateReason);
    }

    public static function blockReasonPages(): array
    {
        return [
            'daily calendar' => ['admin.calendar', 'day'],
            'weekly desktop and mobile calendars' => ['admin.calendar', 'week'],
            'staff availability' => ['admin.staff', 'day'],
        ];
    }

    public function test_demo_logout_can_return_to_guest_booking_but_never_to_an_external_url(): void
    {
        Salon::factory()->create(['demo' => true]);
        config()->set('salon.demo_viewer_enabled', true);
        $viewer = $this->viewer();
        $this->actingAs($viewer)->post(route('logout'), ['return_to' => 'booking'])->assertRedirect(route('booking.form'));
        $this->assertGuest();
        $this->actingAs($viewer)->post(route('logout'), ['return_to' => 'https://attacker.example'])->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_demo_search_does_not_reveal_matches_for_private_guest_identity(): void
    {
        Salon::factory()->create(['demo' => true]);
        config()->set('salon.demo_viewer_enabled', true);
        $guest = Guest::factory()->create(['name' => 'PrivateUniqueGuest', 'phone' => '+36309988776']);
        Booking::factory()->create(['guest_id' => $guest->id]);
        $this->actingAs($this->viewer());
        $this->get(route('admin.guests', ['q' => 'PrivateUniqueGuest']))->assertOk()->assertViewHas('guests', fn ($guests): bool => $guests->isEmpty());
        $this->get(route('admin.bookings.index', ['q' => '36309988776']))->assertOk()->assertViewHas('bookings', fn ($bookings): bool => $bookings->isEmpty());
        $this->get(route('admin.guests', ['q' => 'Minta vendég #'.$guest->id]))->assertOk()->assertViewHas('guests', fn ($guests): bool => $guests->count() === 1);
    }

    public function test_real_owner_can_still_edit_notes_and_sign_in_normally(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner', 'password' => 'owner-secret-password']);
        $guest = Guest::factory()->create();
        $this->post(route('login.submit'), ['email' => $owner->email, 'password' => 'owner-secret-password'])->assertRedirect(route('admin.today'));
        $this->assertAuthenticatedAs($owner);
        $this->post(route('admin.notes', $guest), ['notes' => 'Legitimate owner note'])->assertRedirect();
        $this->assertSame('Legitimate owner note', $guest->fresh()->notes);
    }

    public function test_demo_account_cannot_receive_reset_email_or_reset_using_an_existing_token(): void
    {
        Salon::factory()->create(['demo' => true]);
        $viewer = $this->viewer();
        Notification::fake();
        (new SendPasswordResetLink($viewer->email))->handle(app(OperationsHealth::class));
        Notification::assertNothingSent();
        $token = Password::broker()->createToken($viewer);
        $password = $viewer->getAuthPassword();
        $this->post(route('password.update'), ['email' => $viewer->email, 'token' => $token, 'password' => 'new-unauthorized-password', 'password_confirmation' => 'new-unauthorized-password'])->assertSessionHasErrors('email');
        $this->assertSame($password, $viewer->fresh()->getAuthPassword());
    }

    private function viewer(): User
    {
        return User::factory()->create(['name' => 'Demo viewer', 'email' => 'demo@example.test', 'role' => 'owner', 'is_demo_viewer' => true, 'can_manage_bookings' => false, 'can_view_notes' => false]);
    }
}
