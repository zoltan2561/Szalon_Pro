<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DemoNavigationTest extends TestCase
{
    use RefreshDatabase;

    public static function industries(): array
    {
        return [['salon', 'Fodrászat'], ['barber', 'Barber'], ['nails', 'Körmös']];
    }

    #[DataProvider('industries')]
    public function test_shared_navigation_identifies_each_guest_booking_and_reader_demo(string $theme, string $label): void
    {
        $this->prepareDemo($theme);
        $viewer = User::factory()->create(['role' => 'owner', 'email' => 'demo@example.test', 'is_demo_viewer' => true]);

        foreach (['/?view=guest', '/foglalas', '/admin'] as $path) {
            if ($path === '/admin') {
                $this->actingAs($viewer);
            }
            $response = $this->get($path)->assertOk()
                ->assertSee('data-demo-navigation', false)
                ->assertSee('Most ezt nézed: '.$label.' bemutató')
                ->assertSee('href="https://szalon.pro/"', false)
                ->assertSee('href="https://szalon.pro/?view=guest"', false)
                ->assertSee('href="https://barber.szalon.pro"', false)
                ->assertSee('href="https://nails.szalon.pro"', false)
                ->assertSee('tipus=order&amp;termek=szalonpro&amp;csomag=monthly', false)
                ->assertSee('Admin bemutató ('.$label.')');
            $activeUrl = ['salon' => 'https://szalon.pro/?view=guest', 'barber' => 'https://barber.szalon.pro', 'nails' => 'https://nails.szalon.pro'][$theme];
            $this->assertMatchesRegularExpression('/href="'.preg_quote($activeUrl, '/').'"\s+aria-current="page"/', $response->getContent());
        }
    }

    #[DataProvider('industries')]
    public function test_guest_pages_wrap_the_demo_navigation_in_a_disclosure_that_mobile_collapses(string $theme, string $label): void
    {
        $this->prepareDemo($theme);

        foreach (['/?view=guest', '/foglalas'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<details class="public-demo-navigation" data-mobile-disclosure open><summary><span>SzalonPRO bemutató · '.preg_quote($label, '/').'<small>/u', $html);
            $this->assertStringContainsString('Most ezt nézed: '.$label.' bemutató', $html);
            $this->assertSame(1, substr_count($html, 'data-demo-navigation'));
        }
    }

    public function test_reader_notice_is_shown_on_guest_pages_but_not_on_the_marketing_hub_that_offers_both_entries(): void
    {
        $this->prepareDemo('salon');
        config(['salon.demo_hub' => true]);
        $viewer = User::factory()->create(['role' => 'owner', 'email' => 'demo@example.test', 'is_demo_viewer' => true]);
        $this->actingAs($viewer);

        $this->get('/')->assertOk()->assertSee('Admin bemutató (Fodrászat)')->assertDontSee('Most az olvasó demófiókot használod.');
        $this->get('/foglalas')->assertOk()->assertSee('Most az olvasó demófiókot használod.');
    }

    public function test_reader_menu_marks_owner_only_sections_with_their_own_icons_and_a_single_availability_note(): void
    {
        $this->prepareDemo('salon');
        $viewer = User::factory()->create(['role' => 'owner', 'email' => 'demo@example.test', 'is_demo_viewer' => true]);

        $html = $this->actingAs($viewer)->get('/admin')->assertOk()->getContent();

        $this->assertSame(8, substr_count($html, 'class="menu-locked"'), 'Four sections appear in the sidebar and in the mobile sheet.');
        $this->assertSame(8, substr_count($html, '<small class="menu-availability">Saját szalonnál</small>'));
        $this->assertStringNotContainsString('assets/icons/eye.svg\')" aria-hidden="true"></span><span>Beállítások', $html);
        foreach (['gear-six', 'envelope-simple'] as $icon) {
            $this->assertStringContainsString('assets/icons/'.$icon.'.svg', $html);
        }
    }

    public function test_marketing_page_explains_the_price_inclusions_and_single_contact_destination(): void
    {
        $this->prepareDemo('salon');
        config(['salon.demo_hub' => true]);
        $this->get('/')->assertOk()
            ->assertSee('href="#arak"', false)->assertSee('id="arak"', false)
            ->assertSee('4 900 Ft / hó')
            ->assertSee('Nincs hűségidő, 30 napos felmondással bármikor lemondható.')
            ->assertSee('Online időpontfoglaló és szalonkezelő rendszer fodrászatoknak, barbereknek és körmösöknek.')
            ->assertSee('Google Naptár-szinkron')->assertSee('E-mail-értesítések')
            ->assertSee('tipus=order&amp;termek=szalonpro&amp;csomag=monthly', false)
            ->assertDontSee('mailto:munka@szoftlab.hu', false);
        config(['salon.contact_url' => 'https://example.test/kapcsolat']);
        $this->get('/')->assertSee('tipus=order&amp;termek=szalonpro&amp;csomag=monthly', false)->assertDontSee('href="https://example.test/kapcsolat"', false);
    }

    public function test_restricted_reader_pages_explain_the_limit_in_hungarian_without_exposing_settings(): void
    {
        $salon = $this->prepareDemo('barber');
        $salon->update(['billing_agent_key' => 'private-billing-secret']);
        $viewer = User::factory()->create(['role' => 'owner', 'email' => 'demo@example.test', 'is_demo_viewer' => true]);
        $this->actingAs($viewer);
        foreach (['/admin/beallitasok', '/admin/google-naptar', '/admin/ertesitesek', '/admin/fiok'] as $path) {
            $this->get($path)->assertForbidden()
                ->assertSee('Ez a rész a demóban csak bemutató.')
                ->assertSee('Saját szalonnál elérhető.')
                ->assertSee('Vissza a Mai nap nézetre')
                ->assertSee('data-demo-navigation', false)
                ->assertDontSee('Forbidden')->assertDontSee('private-billing-secret');
        }
        $this->get('/belepes')->assertRedirect(route('admin.today'));
        $this->post('/admin/beallitasok', ['name' => 'Tiltott módosítás'])->assertForbidden()->assertSee('Ez a rész a demóban csak bemutató.');
        $this->assertNotSame('Tiltott módosítás', $salon->fresh()->name);
    }

    public function test_customer_pages_do_not_receive_demo_navigation_or_marketing_pricing(): void
    {
        Salon::factory()->create(['demo' => false]);
        $this->get('/')->assertOk()->assertDontSee('data-demo-navigation', false)->assertDontSee('SzalonPRO: 4 900 Ft/hó');
    }

    public function test_new_service_form_starts_collapsed(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $html = $this->actingAs($owner)->get('/admin/szolgaltatasok')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<details class="panel catalog-item"\s*>\s*<summary class="catalog-summary"><span class="catalog-summary-main"><strong>\+ Új szolgáltatás<\/strong>/u', $html);
    }

    private function prepareDemo(string $theme): Salon
    {
        config(['salon.demo_viewer_enabled' => true, 'salon.demo_urls' => ['salon' => 'https://szalon.pro/', 'barber' => 'https://barber.szalon.pro', 'nails' => 'https://nails.szalon.pro'], 'salon.contact_url' => 'https://szoftlab.hu']);

        return Salon::factory()->create(['demo' => true, 'theme' => $theme]);
    }
}
