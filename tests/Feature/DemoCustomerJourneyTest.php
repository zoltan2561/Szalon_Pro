<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoCustomerJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['salon.demo_urls' => ['salon' => 'https://szalon.pro/', 'barber' => 'https://barber.szalon.pro/', 'nails' => 'https://nails.szalon.pro/']]);
    }

    public function test_guest_entry_uses_allowlisted_destination_and_remains_read_only(): void
    {
        Salon::factory()->create(['demo' => true]);
        config(['salon.demo_viewer_enabled' => true]);
        $viewer = User::factory()->create(['email' => 'demo@example.test', 'role' => 'owner', 'is_demo_viewer' => true]);
        $this->post('/demo-belepes', ['destination' => 'admin.guests'])->assertRedirect(route('admin.guests'));
        $this->assertAuthenticatedAs($viewer);
        $this->get('/admin/vendegek')->assertOk();
        $this->get('/admin')->assertOk()->assertSee('rel="canonical" href="https://szalon.pro/"', false)
            ->assertSee('A SzoftLab terméke')->assertSee('Részletek és árak');
        $this->post('/admin/foglalas', [])->assertForbidden();
        $this->post('/kilepes');
        $this->post('/demo-belepes', ['destination' => 'https://evil.test'])->assertRedirect(route('admin.today'));
    }

    public function test_marketing_contains_accurate_terms_faq_and_placement_specific_quote_links(): void
    {
        $this->demo();
        $html = $this->get('https://szalon.pro/')->assertOk()
            ->assertSee('ÁFA-mentes ár (alanyi adómentes)')
            ->assertSee('Nincs hűségidő, 30 napos felmondással bármikor lemondható.')
            ->assertSee('Így indulsz')->assertSee('Részletek és árak')
            ->assertSee('Demó adatkezelési minta')->assertSee('A SzoftLab terméke')
            ->assertSee('name="destination" value="admin.guests"', false)
            ->assertDontSee('&amp;amp;', false)->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $campaigns = [];
        foreach ($xpath->query('//a[contains(@href,"tipus=order")]') as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            $this->assertSame(['tipus' => 'order', 'termek' => 'szalonpro', 'csomag' => 'monthly', 'utm_source' => 'szalon.pro', 'utm_medium' => 'demo', 'utm_campaign' => $query['utm_campaign']], $query);
            $this->assertStringStartsWith('https://szoftlab.hu/kapcsolat?', $link->getAttribute('href'));
            $campaigns[] = $query['utm_campaign'];
        }
        foreach (['hero', 'pricing', 'footer', 'header', 'contact'] as $placement) {
            $this->assertContains($placement, $campaigns);
        }
        $this->assertSame(3, $xpath->query('//ol[@data-demo-start]/li')->length);
        $schemaFaq = null;
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $schema = json_decode($script->textContent, true, 512, JSON_THROW_ON_ERROR);
            if ($schema['@type'] === 'FAQPage') {
                $schemaFaq = $schema;
            }
        }
        $this->assertCount(5, $schemaFaq['mainEntity']);
        foreach ($schemaFaq['mainEntity'] as $question) {
            $this->assertStringContainsString($question['name'], $dom->textContent);
            $this->assertStringContainsString($question['acceptedAnswer']['text'], $dom->textContent);
        }
    }

    public function test_only_primary_demo_landing_is_indexable_with_metadata_and_monthly_offer(): void
    {
        $this->demo();
        $this->get('https://szalon.pro/')->assertOk()
            ->assertSee('content="index, follow"', false)
            ->assertSee('rel="canonical" href="https://szalon.pro/"', false)
            ->assertSee('property="og:title"', false)->assertSee('SoftwareApplication')
            ->assertSee('"priceCurrency":"HUF"', false)->assertSee('"price":4900', false)
            ->assertSee('"billingDuration":"P1M"', false);
        $this->get('https://szalon.pro/foglalas')->assertOk()->assertSee('content="noindex, follow"', false)
            ->assertHeader('X-Robots-Tag', 'noindex');
        Salon::current()->update(['theme' => 'barber']);
        $this->get('https://barber.szalon.pro/')->assertOk()->assertSee('content="noindex, follow"', false)
            ->assertSee('Barber')->assertSee('rel="canonical" href="https://szalon.pro/"', false);
        $this->get('https://szalon.pro/sitemap.xml')->assertOk()->assertSee('<loc>https://szalon.pro/</loc>', false)->assertDontSee('/admin');
    }

    public function test_customer_sites_receive_no_demo_metadata_or_central_legal_footer(): void
    {
        Salon::factory()->create(['demo' => false]);
        config(['salon.demo_urls' => ['salon' => null, 'barber' => null, 'nails' => null]]);
        $this->get('/')->assertOk()->assertDontSee('https://szalon.pro/', false)
            ->assertDontSee('SoftwareApplication')->assertDontSee('A SzoftLab terméke');
        $this->get('/sitemap.xml')->assertNotFound();
        $this->get('/robots.txt')->assertOk()->assertDontSee('Sitemap: https://szalon.pro/', false);
    }

    public function test_each_demo_host_tracks_its_own_source_and_robots_points_to_the_primary_sitemap(): void
    {
        $this->demo();
        foreach (['salon' => 'szalon.pro', 'barber' => 'barber.szalon.pro', 'nails' => 'nails.szalon.pro'] as $theme => $host) {
            Salon::current()->update(['theme' => $theme]);
            $this->get('https://'.$host.'/')->assertOk()
                ->assertSee('utm_source='.$host.'&amp;utm_medium=demo', false)
                ->assertSee('tipus=question&amp;termek=szalonpro', false)
                ->assertSee('Demó adatkezelési minta')->assertDontSee('&amp;amp;', false);
            $this->get('https://'.$host.'/robots.txt')->assertOk()
                ->assertSee('Sitemap: https://szalon.pro/sitemap.xml', false);
        }
    }

    private function demo(): void
    {
        Salon::factory()->create(['demo' => true]);
        config(['salon.demo_hub' => true, 'salon.demo_viewer_enabled' => true,
            'salon.demo_urls' => ['salon' => 'https://szalon.pro/', 'barber' => 'https://barber.szalon.pro/', 'nails' => 'https://nails.szalon.pro/']]);
        User::factory()->create(['email' => 'demo@example.test', 'role' => 'owner', 'is_demo_viewer' => true]);
    }
}
