<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\User;
use App\Services\TenantContext;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalonPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_customer_has_no_invented_opening_hours_or_public_owner_email(): void
    {
        Salon::factory()->create(['demo' => true]);
        $tenant = app(TenantProvisioner::class)->create('judit', 'Judit Fodrászat', 'owner@example.test');

        app(TenantContext::class)->run($tenant, function (): void {
            $this->assertSame([], Salon::current()->opening_hours);
            $this->assertSame('', Salon::current()->email);
            $this->assertSame('owner@example.test', User::sole()->email);
        });

        $this->get($tenant->url().'/')->assertOk()
            ->assertSee('A nyitvatartás hamarosan elérhető.')
            ->assertSee('Az elérhetőségek hamarosan elérhetők.')
            ->assertDontSee('owner@example.test')
            ->assertDontSee('Zárva')
            ->assertDontSee('mailto:', false);
        $this->get($tenant->url().'/foglalas')->assertOk()->assertSee('Az online időpontfoglalás hamarosan elérhető.')->assertDontSee('keresd a szalont az elérhetőségein');
        $this->get($tenant->url().'/adatkezeles')->assertOk()->assertSee('A tájékoztató hamarosan elérhető.')->assertDontSee('mailto:', false);
    }

    public function test_owner_sees_setup_checklist_in_both_admin_entry_points(): void
    {
        Salon::factory()->create(['demo' => true]);
        $tenant = app(TenantProvisioner::class)->create('judit', 'Judit Fodrászat', 'owner@example.test');
        $owner = app(TenantContext::class)->run($tenant, fn (): User => User::sole());

        foreach (['/szalon-admin', '/admin'] as $path) {
            $this->actingAs($owner)->get($tenant->url().$path)->assertOk()
                ->assertSee('A szalonod indulási teendői')
                ->assertSee('Szolgáltatások és árak')
                ->assertSee('Cím és elérhetőség')
                ->assertSee('Nyitvatartás')
                ->assertSee('Munkatársak és munkarend')
                ->assertSee('Google Naptár összekötése');
        }
        $this->get($tenant->url().'/admin/google-naptar')->assertOk()->assertSee('Előbb vegyél fel aktív szakembert');
    }

    public function test_existing_demo_keeps_its_opening_hours_and_has_no_new_setup_checklist(): void
    {
        Salon::factory()->create(['demo' => true]);
        $owner = User::factory()->create(['role' => 'owner']);
        $this->get('/')->assertOk()->assertSee('09:00–18:00')->assertDontSee('A nyitvatartás hamarosan elérhető.');
        $this->actingAs($owner)->get('/admin')->assertOk()->assertDontSee('A szalonod indulási teendői');
    }
}
