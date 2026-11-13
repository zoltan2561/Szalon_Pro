<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HungarianErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_calendar_date_shows_a_hungarian_page_with_a_way_back(): void
    {
        Salon::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'owner']));

        $this->get(route('admin.calendar', ['date' => '2026-02-30']))
            ->assertStatus(422)
            ->assertSee('Ezt a kérést nem tudtuk értelmezni')
            ->assertSee(route('admin.calendar'), false)
            ->assertDontSee('An Error Occurred')
            ->assertDontSee('Oops');
    }

    public function test_staff_member_opening_an_owner_page_sees_a_hungarian_forbidden_page(): void
    {
        Salon::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_id' => Staff::factory()]));

        $this->get(route('admin.services'))
            ->assertForbidden()
            ->assertSee('Ehhez nincs jogosultságod')
            ->assertSee(route('admin.today'), false)
            ->assertDontSee('Forbidden');
    }

    public function test_wrong_http_method_shows_a_hungarian_page_without_an_admin_link_for_guests(): void
    {
        Salon::factory()->create();

        $this->post(route('booking.success'))
            ->assertStatus(405)
            ->assertSee('Ez a művelet itt nem használható')
            ->assertDontSee('Method Not Allowed')
            ->assertDontSee(route('admin.today'), false);
    }
}
