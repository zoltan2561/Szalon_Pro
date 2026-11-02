<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisioning_creates_an_empty_independent_salon_and_owner(): void
    {
        $demo = Salon::factory()->create();
        Guest::factory()->create(['name' => 'Demo guest']);
        $tenant = app(TenantProvisioner::class)->create('vanessa', 'Vanessa Szalon', 'vanessa@example.test');
        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            $this->assertSame('Vanessa Szalon', Salon::current()->name);
            $this->assertSame($tenant->id, Salon::current()->tenant_id);
            $this->assertSame(0, Guest::count());
            $this->assertSame(0, Service::count());
            $this->assertSame(0, Staff::count());
            $this->assertSame('vanessa@example.test', User::sole()->email);
            $this->assertFalse(User::sole()->is_super_admin);
        });
        $this->assertSame($demo->id, Salon::current()->id);
        $this->assertSame('Demo guest', Guest::sole()->name);
    }

    public function test_unknown_and_suspended_hosts_never_fall_back_to_demo(): void
    {
        Salon::factory()->create();
        $tenant = app(TenantProvisioner::class)->create('vanessa', 'Vanessa Szalon', 'vanessa@example.test');
        $this->get('http://unknown.localhost/')->assertNotFound();
        $this->get($tenant->url().'/')->assertOk()->assertSee('Vanessa Szalon')->assertDontSee('Teszt Szalon');
        $tenant->update(['status' => 'suspended']);
        $this->get($tenant->url().'/')->assertStatus(503);
        $this->get(Tenant::findOrFail(1)->url().'/')->assertOk()->assertSee('Teszt Szalon');
    }

    public function test_foreign_ids_validation_and_pivots_are_rejected(): void
    {
        Salon::factory()->create();
        $staff = Staff::factory()->create();
        $tenant = app(TenantProvisioner::class)->create('other', 'Other salon', 'owner@example.test');
        app(TenantContext::class)->run($tenant, function () use ($staff): void {
            $this->assertNull(Staff::find($staff->id));
            $this->assertTrue(Validator::make(['staff_id' => $staff->id], ['staff_id' => 'exists:staff,id'])->fails());
            $service = Service::factory()->create();
            $this->expectException(ValidationException::class);
            $service->staff()->attach($staff->id);
        });
    }

    public function test_context_and_url_are_restored_after_exceptions(): void
    {
        Salon::factory()->create();
        $tenant = app(TenantProvisioner::class)->create('other', 'Other salon', 'owner@example.test');
        $url = url('/foglalas');
        try {
            app(TenantContext::class)->run($tenant, function () use ($tenant): void {
                $this->assertSame($tenant->url().'/foglalas', route('booking.form'));
                throw new \RuntimeException('Expected');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame(1, app(TenantContext::class)->id());
        $this->assertSame($url, url('/foglalas'));
    }

    public function test_existing_data_uses_demo_and_keeps_its_content(): void
    {
        $salon = Salon::factory()->create(['name' => 'Original salon', 'appearance' => ['paper' => '#123456']]);
        $this->assertSame(1, $salon->tenant_id);
        $this->assertSame('demo', Tenant::findOrFail(1)->slug);
        $this->assertSame(['paper' => '#123456'], $salon->fresh()->appearance);
    }

    public function test_demo_email_cannot_alias_another_customers_reset_identity(): void
    {
        Salon::factory()->create();
        $tenant = app(TenantProvisioner::class)->create('private', 'Private salon', 'victim@example.test');
        $alias = User::factory()->create(['email' => $tenant->id.'|victim@example.test']);
        $token = Password::broker()->createToken($alias);
        app(TenantContext::class)->run($tenant, function () use ($alias, $token): void {
            $victim = User::sole();
            $this->assertNotSame($alias->getEmailForPasswordReset(), $victim->getEmailForPasswordReset());
            $this->assertFalse(Password::broker()->tokenExists($victim, $token));
        });
    }

    public function test_same_email_login_and_foreign_record_urls_are_isolated(): void
    {
        Salon::factory()->create();
        $demoOwner = User::factory()->create(['role' => 'owner', 'email' => 'same@example.test']);
        $guest = Guest::factory()->create(['name' => 'Hidden guest']);
        $tenant = app(TenantProvisioner::class)->create('private', 'Private salon', 'same@example.test');
        $owner = app(TenantContext::class)->run($tenant, function (): User {
            $owner = User::sole();
            $owner->update(['password' => 'Customer-test-password-2026!']);

            return $owner;
        });
        $this->post($tenant->url().'/belepes', ['email' => 'same@example.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post($tenant->url().'/belepes', ['email' => 'same@example.test', 'password' => 'Customer-test-password-2026!'])->assertRedirect();
        $this->assertAuthenticatedAs($owner);
        $this->get($tenant->url().'/admin/vendegek/'.$guest->id)->assertNotFound();
        $this->get($tenant->url().'/admin/vendegek')->assertOk()->assertDontSee('Hidden guest');
        $this->flushSession();
        $this->actingAs($demoOwner)->get($tenant->url().'/admin')->assertForbidden();
    }

    public function test_configured_demo_domain_cannot_be_taken_by_a_new_customer(): void
    {
        config(['tenancy.base_domain' => 'example.test', 'tenancy.demo_domains' => ['hair.example.test']]);
        $this->expectException(ValidationException::class);
        app(TenantProvisioner::class)->create('hair', 'Customer salon', 'owner@example.test');
    }
}
