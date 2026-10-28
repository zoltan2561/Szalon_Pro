<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_reorder_services_and_staff_and_the_public_list_uses_the_order(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $first = Service::factory()->create(['name' => 'Első kezelés']);
        $second = Service::factory()->create(['name' => 'Második kezelés']);
        $person = Staff::factory()->create(['name' => 'Első munkatárs']);
        $other = Staff::factory()->create(['name' => 'Második munkatárs']);
        $this->actingAs($owner)->post(route('admin.services.move', $second), ['direction' => 'up'])->assertRedirect();
        $this->post(route('admin.staff.move', $other), ['direction' => 'up'])->assertRedirect();
        $this->assertLessThan($first->fresh()->sort_order, $second->fresh()->sort_order);
        $this->assertLessThan($person->fresh()->sort_order, $other->fresh()->sort_order);
        $this->get(route('booking.form'))->assertOk()->assertSeeInOrder(['Második kezelés', 'Első kezelés']);
        $this->get(route('admin.staff'))->assertOk()->assertSee('H: 09:00–18:00');
    }

    public function test_archiving_preserves_existing_bookings_and_can_be_reversed(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $service = Service::factory()->create();
        $staff = $service->staff()->first();
        $booking = Booking::factory()->create(['service_id' => $service->id, 'staff_id' => $staff->id]);
        $this->actingAs($owner)->post(route('admin.services.archive', $service), ['active' => 0])->assertRedirect();
        $this->post(route('admin.staff.archive', $staff), ['active' => 0])->assertRedirect();
        $this->assertFalse($service->fresh()->active);
        $this->assertFalse($staff->fresh()->active);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'service_id' => $service->id, 'staff_id' => $staff->id]);
        $this->post(route('admin.services.archive', $service), ['active' => 1])->assertRedirect();
        $this->assertTrue($service->fresh()->active);
    }

    public function test_delete_requires_confirmation_and_rejects_any_linked_data(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $service = Service::factory()->create();
        $staff = $service->staff()->first();
        $this->actingAs($owner)->delete(route('admin.services.delete', $service))->assertSessionHasErrors('confirm');
        $this->delete(route('admin.services.delete', $service), ['confirm' => 1])->assertSessionHasErrors('catalog');
        $this->delete(route('admin.staff.delete', $staff), ['confirm' => 1])->assertSessionHasErrors('catalog');
        $this->assertDatabaseHas('service_staff', ['service_id' => $service->id, 'staff_id' => $staff->id]);
        $service->staff()->detach();
        $this->delete(route('admin.services.delete', $service), ['confirm' => 1])->assertSessionHasNoErrors();
        $this->delete(route('admin.staff.delete', $staff), ['confirm' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('services', ['id' => $service->id]);
        $this->assertDatabaseMissing('staff', ['id' => $staff->id]);
    }

    public function test_staff_cannot_manage_the_catalog(): void
    {
        Salon::factory()->create();
        $staff = Staff::factory()->create();
        $user = User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id]);
        $service = Service::factory()->create();
        $this->actingAs($user)->post(route('admin.services.move', $service), ['direction' => 'up'])->assertForbidden();
        $this->post(route('admin.staff.archive', $staff), ['active' => 0])->assertForbidden();
        $this->delete(route('admin.services.delete', $service), ['confirm' => 1])->assertForbidden();
    }

    public function test_booking_history_blocks_deletion_even_after_service_staff_link_is_removed(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $service = Service::factory()->create();
        $staff = $service->staff()->first();
        $booking = Booking::factory()->create(['service_id' => $service->id, 'staff_id' => $staff->id]);
        $service->staff()->detach();
        $this->actingAs($owner)->delete(route('admin.services.delete', $service), ['confirm' => 1])->assertSessionHasErrors('catalog');
        $this->delete(route('admin.staff.delete', $staff), ['confirm' => 1])->assertSessionHasErrors('catalog');
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'service_id' => $service->id, 'staff_id' => $staff->id]);
    }
}
