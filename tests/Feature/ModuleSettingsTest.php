<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owner_can_toggle_optional_modules_without_erasing_their_data(): void
    {
        $salon = Salon::factory()->create(['features' => ['future_module' => true]]);
        $staff = User::factory()->create(['role' => 'staff']);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($staff)->get(route('admin.modules.index'))->assertForbidden();
        $this->post(route('admin.modules.update'), ['features' => ['passes' => '1']])->assertForbidden();

        $this->actingAs($owner)->get(route('admin.modules.index'))->assertOk()->assertSee('Hűségpontok');
        $this->post(route('admin.modules.update'), ['features' => ['passes' => '1', 'loyalty' => '1', 'waitlist' => '1']])->assertRedirect();
        $this->assertTrue($salon->fresh()->featureEnabled('passes'));
        $this->assertTrue($salon->fresh()->featureEnabled('loyalty'));
        $this->assertTrue($salon->fresh()->featureEnabled('waitlist'));
        $this->assertFalse($salon->fresh()->featureEnabled('financials'));

        $this->post(route('admin.modules.update'))->assertRedirect();
        $this->assertFalse($salon->fresh()->featureEnabled('passes'));
        $this->assertTrue($salon->fresh()->featureEnabled('future_module'));
    }
}
