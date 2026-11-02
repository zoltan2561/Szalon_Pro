<?php

namespace Tests\Feature;

use App\Filament\SuperAdmin\Resources\TenantResource\Pages\CreateTenant;
use App\Filament\SuperAdmin\Resources\TenantResource\Pages\EditTenant;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use App\Services\TenantProvisioner;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TenantAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_separate_empty_customer_from_the_filament_form(): void
    {
        Salon::factory()->create();
        $superAdmin = User::factory()->create(['role' => 'owner']);
        $superAdmin->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($superAdmin);
        Filament::setCurrentPanel(Filament::getPanel('super'));

        Livewire::test(CreateTenant::class)
            ->fillForm(['slug' => 'vanessa-test', 'name' => 'Vanessa teszt', 'admin_email' => 'vanessa@example.test'])
            ->call('create')
            ->assertHasNoFormErrors();

        $tenant = Tenant::where('slug', 'vanessa-test')->sole();
        app(TenantContext::class)->run($tenant, function (): void {
            $this->assertSame('Vanessa teszt', Salon::current()->name);
            $this->assertSame(0, Guest::count());
            $owner = User::where('email', 'vanessa@example.test')->sole();
            $this->assertSame('owner', $owner->role);
            $this->assertFalse((bool) $owner->is_super_admin);
        });
    }

    public function test_tenant_owner_cannot_open_or_mutate_super_admin_resources(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/foadmin/tenants')->assertForbidden();
        Filament::setCurrentPanel(Filament::getPanel('super'));
        Livewire::test(CreateTenant::class)->assertForbidden();
    }

    public function test_super_admin_can_suspend_and_reactivate_without_deleting_customer_data(): void
    {
        Salon::factory()->create();
        $superAdmin = User::factory()->create(['role' => 'owner']);
        $superAdmin->forceFill(['is_super_admin' => true])->save();
        $tenant = app(TenantProvisioner::class)->create('private-salon', 'Saját szalon', 'owner@example.test');
        $this->actingAs($superAdmin);
        Filament::setCurrentPanel(Filament::getPanel('super'));

        Livewire::test(EditTenant::class, ['record' => $tenant->id])
            ->fillForm(['name' => 'Átnevezett szalon', 'status' => 'suspended'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('suspended', $tenant->fresh()->status);
        $this->get($tenant->url().'/')->assertStatus(503);

        Livewire::test(EditTenant::class, ['record' => $tenant->id])
            ->fillForm(['name' => 'Átnevezett szalon', 'status' => 'active'])
            ->call('save')->assertHasNoFormErrors();
        app(TenantContext::class)->run($tenant->fresh(), function (): void {
            $this->assertSame('Átnevezett szalon', Salon::current()->name);
            $this->assertSame(1, User::count());
        });
    }

    public function test_impersonation_ticket_is_single_use_and_bound_to_the_selected_domain(): void
    {
        Salon::factory()->create();
        $superAdmin = User::factory()->create(['role' => 'owner']);
        $superAdmin->forceFill(['is_super_admin' => true])->save();
        $tenant = app(TenantProvisioner::class)->create('private-salon', 'Saját szalon', 'owner@example.test');
        $other = app(TenantProvisioner::class)->create('other-salon', 'Másik szalon', 'owner@example.test');

        $response = $this->actingAs($superAdmin)->post(route('tenancy.impersonate.start', $tenant));
        $response->assertRedirect();
        $target = $response->headers->get('Location');
        $this->assertStringStartsWith($tenant->url().'/foadmin/atvetel/', $target);
        $this->assertDatabaseCount('tenant_impersonations', 1);

        auth()->forgetGuards();
        $this->get(str_replace($tenant->domain, $other->domain, $target))->assertNotFound();
        $this->get($target)->assertRedirect($tenant->url().'/szalon-admin');
        $this->assertNotNull(DB::table('tenant_impersonations')->value('used_at'));
        $this->get($target)->assertNotFound();
    }

    public function test_tenant_owner_and_expired_ticket_cannot_impersonate(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $tenant = app(TenantProvisioner::class)->create('private-salon', 'Saját szalon', 'owner@example.test');
        $this->actingAs($owner)->post(route('tenancy.impersonate.start', $tenant))->assertForbidden();

        $owner->forceFill(['is_super_admin' => true])->save();
        $response = $this->post(route('tenancy.impersonate.start', $tenant));
        DB::table('tenant_impersonations')->update(['expires_at' => now()->subMinute()]);
        auth()->forgetGuards();
        $this->get($response->headers->get('Location'))->assertNotFound();
    }

    public function test_customer_panel_is_available_to_its_owner_with_existing_workflow_links(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->get('/szalon-admin')->assertOk()
            ->assertSee('Mai nap')
            ->assertSee('Arculat és tartalom')->assertDontSee('ui-avatars.com')->assertDontSee('filament-panels::layout');
    }

    public function test_filament_scripts_have_a_nonce_without_weakening_guest_page_policy(): void
    {
        Salon::factory()->create();
        $response = $this->get('/foadmin/login')->assertOk();
        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'/", $policy);
        preg_match("/'nonce-([^']+)'/", $policy, $matches);
        $response->assertSee('nonce="'.$matches[1].'"', false)->assertHeader('X-Robots-Tag', 'noindex');
        $this->assertStringNotContainsString('unsafe-eval', $this->get('/')->headers->get('Content-Security-Policy'));
    }

    public function test_existing_demo_cannot_be_edited_from_the_super_admin(): void
    {
        $salon = Salon::factory()->create(['name' => 'Original demo']);
        $owner = User::factory()->create(['role' => 'owner']);
        $owner->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($owner)->get('/foadmin/tenants/1/edit')->assertForbidden();
        $this->assertSame('Original demo', $salon->fresh()->name);
    }
}
