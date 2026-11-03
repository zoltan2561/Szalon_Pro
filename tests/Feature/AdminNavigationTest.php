<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pagination_has_hungarian_labels_and_preserves_search_and_sort(): void
    {
        Salon::factory()->create();
        Guest::factory()->count(31)->create(['name' => 'Keresett Vendég']);
        $this->actingAs(User::factory()->create(['role' => 'owner']));

        $response = $this->get(route('admin.guests', ['q' => 'Keresett', 'sort' => 'last_visit']))->assertOk();
        $response->assertSee('aria-label="Lapozás"', false)->assertSee('Következő oldal')->assertDontSee('pagination.previous');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $next = $xpath->query('//nav[@aria-label="Lapozás"]//a[@rel="next"]');
        $this->assertCount(1, $next);
        parse_str(parse_url($next->item(0)->getAttribute('href'), PHP_URL_QUERY), $query);
        $this->assertSame(['q' => 'Keresett', 'sort' => 'last_visit', 'page' => '2'], $query);
        $this->assertCount(0, $xpath->query('//nav[@aria-label="Lapozás"]//span[@aria-label]'));

        $this->get($next->item(0)->getAttribute('href'))->assertOk()->assertSee('Előző oldal')->assertViewHas('guests', fn ($guests) => $guests->count() === 1);
    }

    public function test_staff_more_sheet_respects_role_permissions_and_module_flags(): void
    {
        $salon = Salon::factory()->create(['features' => ['waitlist' => true, 'passes' => true]]);
        $staff = User::factory()->create(['role' => 'staff', 'staff_id' => Staff::factory(), 'can_manage_bookings' => false]);
        $this->actingAs($staff)->get(route('admin.today'))->assertOk()->assertSee('admin-more-menu')->assertSee('Várólista')
            ->assertDontSee('Beállítások')->assertSee('Saját Google Naptár')->assertDontSee('Bérletek és hűségpontok')
            ->assertDontSee('aria-label="Új foglalás"', false);
        $salon->update(['features' => ['waitlist' => false]]);
        $this->get(route('admin.today'))->assertDontSee('Várólista');
    }

    public function test_owner_navigation_has_active_state_and_primary_booking_action(): void
    {
        Salon::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('admin.today'))->assertOk()
            ->assertSee('aria-current="page"', false)->assertSee('aria-label="Új foglalás"', false)->assertSee('További lehetőségek');
    }

    public function test_owner_navigation_shows_enabled_modules_and_google_is_in_settings(): void
    {
        Salon::factory()->create(['features' => ['waitlist' => true, 'billing' => true, 'vouchers' => false]]);
        $owner = User::factory()->create(['role' => 'owner', 'name' => 'Tulajdonos']);

        $this->actingAs($owner)->get(route('admin.services'))->assertOk()
            ->assertSee('href="'.route('admin.waitlist.index').'"', false)
            ->assertSee('href="'.route('admin.billing.index').'"', false)
            ->assertDontSee('href="'.route('admin.vouchers.index').'"', false)
            ->assertSee('href="'.route('admin.modules.index').'"', false)
            ->assertDontSee('href="'.route('admin.google.index').'"', false)
            ->assertDontSee('<small>Tulajdonos</small>', false);

        $this->get(route('admin.settings'))->assertOk()->assertSee('href="'.route('admin.google.index').'"', false);
    }

    public function test_tomorrows_next_guest_is_clearly_labeled_and_has_a_call_link_when_a_phone_is_available(): void
    {
        Salon::factory()->create();
        $guest = Guest::factory()->create(['phone' => '+36 30 555 0001']);
        Booking::factory()->create(['guest_id' => $guest->id]);

        $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('admin.today'))
            ->assertOk()->assertSee('KÖVETKEZŐ VENDÉG · Holnap')->assertSee('tel:+36305550001', false);
    }
}
