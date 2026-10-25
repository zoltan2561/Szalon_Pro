<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_more_sheet_respects_role_permissions_and_module_flags(): void
    {
        $salon = Salon::factory()->create(['features' => ['waitlist' => true, 'passes' => true]]);
        $staff = User::factory()->create(['role' => 'staff', 'staff_id' => Staff::factory(), 'can_manage_bookings' => false]);
        $this->actingAs($staff)->get(route('admin.today'))->assertOk()->assertSee('admin-more-menu')->assertSee('Várólista')
            ->assertDontSee('Beállítások')->assertDontSee('Google Naptár</a>', false)->assertDontSee('Bérletek és hűségpontok')
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
}
