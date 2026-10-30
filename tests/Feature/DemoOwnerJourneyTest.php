<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoOwnerJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_demo_offers_guest_and_owner_paths_without_replacing_the_guest_home(): void
    {
        Salon::factory()->create(['demo' => true]);
        Service::factory()->create(['name' => 'Próba hajvágás']);
        config()->set('salon.demo_hub', true);
        config()->set('salon.demo_urls', ['salon' => 'https://hair.example.test', 'barber' => 'https://barber.example.test', 'nails' => 'https://nails.example.test']);
        config()->set('salon.demo_sales_contact', 'https://example.test/kapcsolat');

        $this->get(route('home'))->assertOk()
            ->assertSee('data-demo-hub', false)
            ->assertSee('href="'.route('booking.form').'"', false)
            ->assertSee('href="'.route('admin.today').'"', false)
            ->assertSee('href="https://barber.example.test"', false)
            ->assertSee('href="https://nails.example.test"', false)
            ->assertSee('href="https://example.test/kapcsolat"', false);

        $this->get(route('home', ['view' => 'guest']))->assertOk()
            ->assertDontSee('data-demo-hub', false)->assertSee('Próba hajvágás');
        $this->get(route('admin.today'))->assertRedirect(route('login'));
    }

    public function test_customer_sites_keep_their_brand_even_if_the_hub_setting_is_enabled(): void
    {
        Salon::factory()->create(['demo' => false, 'name' => 'Saját Fodrászatom']);
        Service::factory()->create();
        config()->set('salon.demo_hub', true);

        $this->get(route('home'))->assertOk()->assertSee('Saját Fodrászatom')
            ->assertDontSee('data-demo-hub', false)->assertDontSee('Tulajdonosi bemutató');
    }

    public function test_booking_trial_keeps_the_current_industry_instead_of_offering_another_database(): void
    {
        Salon::factory()->create(['demo' => true]);
        Service::factory()->create();
        config()->set('salon.demo_urls', ['salon' => 'https://hair.example.test', 'barber' => 'https://barber.example.test', 'nails' => 'https://nails.example.test']);

        $this->get(route('booking.form'))->assertOk()
            ->assertDontSee('class="demo-style-nav"', false)
            ->assertSee('Vissza a bemutatóhoz');
    }
}
