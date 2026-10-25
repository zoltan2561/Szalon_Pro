<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_download_all_guest_data_but_staff_cannot(): void
    {
        Salon::factory()->create();
        $booking = Booking::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $response = $this->actingAs($owner)->get(route('admin.export', $booking->guest));
        $response->assertOk()->assertDownload('vendeg-'.$booking->guest_id.'.json');
        $export = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR);
        foreach (['guest', 'visits', 'passes', 'loyalty_entries', 'sms_notifications'] as $key) {
            $this->assertArrayHasKey($key, $export);
        }
        $this->assertSame($booking->guest_id, $export['guest']['id']);
        $this->assertArrayNotHasKey('manage_token', $export['visits'][0]);

        $staff = User::factory()->create(['role' => 'staff', 'staff_id' => $booking->staff_id]);
        $this->actingAs($staff)->get(route('admin.export', $booking->guest))->assertForbidden();
    }
}
