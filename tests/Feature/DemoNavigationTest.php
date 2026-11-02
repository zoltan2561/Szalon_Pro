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
                ->assertSee('href="https://szalon.pro/#arak"', false)
                ->assertSee('Árak')
                ->assertSee('href="https://szoftlab.hu"', false)
                ->assertSee('Admin bemutató ('.$label.')');
            $activeUrl = ['salon' => 'https://szalon.pro/?view=guest', 'barber' => 'https://barber.szalon.pro', 'nails' => 'https://nails.szalon.pro'][$theme];
            $this->assertMatchesRegularExpression('/href="'.preg_quote($activeUrl, '/').'"\s+aria-current="page"/', $response->getContent());
        }
    }

    public function test_marketing_page_explains_the_price_inclusions_and_single_contact_destination(): void
    {
        $this->prepareDemo('salon');
        config(['salon.demo_hub' => true]);
        $this->get('/')->assertOk()
            ->assertSee('href="#arak"', false)->assertSee('id="arak"', false)
            ->assertSee('4 900 Ft / hó')
            ->assertSee('Hűségidő nélkül, bevezetési díj nélkül. Bármikor lemondható.')
            ->assertSee('Online időpontfoglaló és szalonkezelő rendszer fodrászatoknak, barbereknek és körmösöknek.')
            ->assertSee('Google Naptár-szinkron')->assertSee('E-mail-értesítések')
            ->assertSee('href="https://szoftlab.hu"', false)
            ->assertDontSee('mailto:munka@szoftlab.hu', false);
        config(['salon.contact_url' => 'https://example.test/kapcsolat']);
        $this->get('/')->assertSee('href="https://example.test/kapcsolat"', false)->assertDontSee('href="https://szoftlab.hu"', false);
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
        $this->assertMatchesRegularExpression('/<details class="panel"\s*>\s*<summary>\+ Új szolgáltatás<\/summary>/u', $html);
    }

    private function prepareDemo(string $theme): Salon
    {
        config(['salon.demo_viewer_enabled' => true, 'salon.demo_urls' => ['salon' => 'https://szalon.pro/', 'barber' => 'https://barber.szalon.pro', 'nails' => 'https://nails.szalon.pro'], 'salon.contact_url' => 'https://szoftlab.hu']);

        return Salon::factory()->create(['demo' => true, 'theme' => $theme]);
    }
}
