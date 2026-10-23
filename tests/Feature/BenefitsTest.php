<?php

namespace Tests\Feature;

use App\Models\BenefitSettings;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\GuestPass;
use App\Models\PassPlan;
use App\Models\PassUse;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\BenefitsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BenefitsTest extends TestCase
{
    use RefreshDatabase;

    private Salon $salon;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->salon = Salon::factory()->create(['id' => 1, 'features' => ['passes' => true, 'loyalty' => true]]);
        $this->owner = User::factory()->create(['role' => 'owner']);
    }

    public function test_completed_booking_uses_one_pass_visit_and_awards_points_once_with_reversible_entries(): void
    {
        $service = Service::factory()->create(['loyalty_points' => 2]);
        $guest = Guest::factory()->create();
        $plan = PassPlan::factory()->create(['service_id' => $service->id, 'visits_total' => 5]);
        BenefitSettings::updateOrCreate(['salon_id' => 1], ['loyalty_reward_threshold' => 2, 'loyalty_reward_label' => 'Extra kezelés']);
        $benefits = app(BenefitsService::class);
        $issueKey = (string) Str::uuid();
        $pass = $benefits->issuePass($guest, $plan, $this->owner, $issueKey);
        $this->assertSame($pass->id, $benefits->issuePass($guest, $plan, $this->owner, $issueKey)->id);
        $this->assertDatabaseCount('guest_passes', 1);

        $booking = Booking::factory()->create(['guest_id' => $guest->id, 'service_id' => $service->id, 'status' => 'completed']);
        $benefits->completeBooking($booking, $pass->id);
        $benefits->completeBooking($booking, $pass->id);
        $this->assertSame(4, $pass->fresh()->visits_remaining);
        $this->assertSame(2, $benefits->loyaltyBalance($guest));
        $this->assertDatabaseCount('pass_uses', 1);
        $this->assertDatabaseCount('loyalty_entries', 1);

        $rewardKey = (string) Str::uuid();
        $reward = $benefits->redeemReward($guest, $rewardKey);
        $this->assertSame($reward->id, $benefits->redeemReward($guest, $rewardKey)->id);
        $this->assertSame(0, $benefits->loyaltyBalance($guest));

        $this->salon->update(['features' => ['passes' => false, 'loyalty' => false]]);
        $booking->update(['status' => 'cancelled']);
        $benefits->reverseBooking($booking);
        $benefits->reverseBooking($booking);
        $this->assertSame(5, $pass->fresh()->visits_remaining);
        $this->assertNotNull(PassUse::firstOrFail()->reversed_at);
        $this->assertSame(-2, $benefits->loyaltyBalance($guest));
        $this->assertDatabaseCount('loyalty_entries', 3);
    }

    public function test_wrong_guest_expired_pass_and_disabled_modules_do_not_consume_visits(): void
    {
        $service = Service::factory()->create();
        $otherGuest = Guest::factory()->create();
        $guest = Guest::factory()->create();
        $pass = app(BenefitsService::class)->issuePass($otherGuest, PassPlan::factory()->create(['service_id' => $service->id]), $this->owner, (string) Str::uuid());
        $booking = Booking::factory()->create(['guest_id' => $guest->id, 'service_id' => $service->id, 'status' => 'completed']);

        try {
            app(BenefitsService::class)->completeBooking($booking, $pass->id);
            $this->fail('Más vendég bérlete nem használható.');
        } catch (ValidationException) {
            $this->assertSame(5, $pass->fresh()->visits_remaining);
            $this->assertDatabaseCount('loyalty_entries', 0);
        }

        $pass->update(['guest_id' => $guest->id, 'expires_at' => now()->subDay()]);
        $this->expectException(ValidationException::class);
        app(BenefitsService::class)->completeBooking($booking, $pass->id);
    }

    public function test_disabled_modules_keep_existing_balances_without_new_awards(): void
    {
        $this->salon->update(['features' => ['passes' => false, 'loyalty' => false]]);
        $booking = Booking::factory()->create(['status' => 'completed']);
        app(BenefitsService::class)->completeBooking($booking);
        $this->assertDatabaseCount('loyalty_entries', 0);
        $this->assertDatabaseCount('pass_uses', 0);
        $this->assertSame(0, app(BenefitsService::class)->loyaltyBalance($booking->guest));
    }

    public function test_anonymized_guest_does_not_receive_new_points_or_pass_redemption(): void
    {
        $guest = Guest::factory()->create();
        $plan = PassPlan::factory()->create();
        $benefits = app(BenefitsService::class);
        $pass = $benefits->issuePass($guest, $plan, $this->owner, (string) Str::uuid());
        $guest->update(['anonymized_at' => now()]);
        $booking = Booking::factory()->create(['guest_id' => $guest->id, 'service_id' => $plan->service_id, 'status' => 'completed']);
        $benefits->completeBooking($booking);
        $this->assertDatabaseCount('loyalty_entries', 0);
        $this->assertCount(0, $benefits->usablePassesFor($booking));
        try {
            $benefits->completeBooking($booking, $pass->id);
            $this->fail('Törölt vendégkartonhoz nem használható bérlet.');
        } catch (ValidationException) {
            $this->assertSame(5, $pass->fresh()->visits_remaining);
        }
    }

    public function test_plan_changes_do_not_change_an_issued_pass(): void
    {
        $plan = PassPlan::factory()->create(['visits_total' => 6, 'validity_days' => 90]);
        $guest = Guest::factory()->create();
        $pass = app(BenefitsService::class)->issuePass($guest, $plan, $this->owner, (string) Str::uuid());
        $plan->update(['name' => 'Új név', 'visits_total' => 9, 'validity_days' => 30]);
        $this->assertSame(6, $pass->fresh()->visits_total);
        $this->assertSame(6, $pass->visits_remaining);
        $this->assertNotSame($plan->name, $pass->name);
    }

    public function test_reward_requires_enough_points_and_is_atomic(): void
    {
        $guest = Guest::factory()->create();
        $benefits = app(BenefitsService::class);
        try {
            $benefits->redeemReward($guest, (string) Str::uuid());
            $this->fail('Pont nélküli jutalom nem váltható be.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('loyalty_entries', 0);
        }
    }

    public function test_direct_reward_and_pass_operations_remain_tied_to_one_guest(): void
    {
        $guest = Guest::factory()->create();
        $other = Guest::factory()->create();
        $plan = PassPlan::factory()->create();
        $benefits = app(BenefitsService::class);
        $key = (string) Str::uuid();
        $benefits->issuePass($guest, $plan, $this->owner, $key);
        $this->expectException(ValidationException::class);
        $benefits->issuePass($other, $plan, $this->owner, $key);
    }

    public function test_no_show_does_not_earn_points_or_consume_a_pass(): void
    {
        $guest = Guest::factory()->create();
        $plan = PassPlan::factory()->create();
        $benefits = app(BenefitsService::class);
        $pass = $benefits->issuePass($guest, $plan, $this->owner, (string) Str::uuid());
        $booking = Booking::factory()->create(['guest_id' => $guest->id, 'service_id' => $plan->service_id, 'status' => 'no_show']);
        try {
            $benefits->completeBooking($booking, $pass->id);
            $this->fail('A nem megjelent vendéghez nem számolható el juttatás.');
        } catch (ValidationException) {
            $this->assertSame(5, $pass->fresh()->visits_remaining);
            $this->assertDatabaseCount('pass_uses', 0);
            $this->assertDatabaseCount('loyalty_entries', 0);
        }
    }

    public function test_owner_can_configure_plans_issue_passes_and_redeem_rewards_from_admin(): void
    {
        $service = Service::factory()->create();
        $guest = Guest::factory()->create();
        $this->actingAs($this->owner);
        $this->get(route('admin.benefits.index'))->assertOk()->assertSee('Bérletek és hűségpontok');
        $this->post(route('admin.benefits.settings'), ['loyalty_points_per_visit' => 2, 'loyalty_reward_threshold' => 2, 'loyalty_reward_label' => 'Ajándék alkalom'])->assertSessionHasNoErrors();
        $this->assertSame(2, BenefitSettings::current()->loyalty_reward_threshold);
        $this->post(route('admin.benefits.points', $service), ['loyalty_points' => 2])->assertSessionHasNoErrors();
        $this->assertSame(2, $service->fresh()->loyalty_points);
        $this->post(route('admin.benefits.plan'), ['name' => 'Öt alkalmas', 'service_id' => $service->id, 'visits_total' => 5, 'validity_days' => 90, 'active' => 1])->assertSessionHasNoErrors();
        $plan = PassPlan::firstOrFail();

        $key = (string) Str::uuid();
        $this->post(route('admin.benefits.issue', $guest), ['pass_plan_id' => $plan->id, 'request_key' => $key])->assertSessionHasNoErrors();
        $this->post(route('admin.benefits.issue', $guest), ['pass_plan_id' => $plan->id, 'request_key' => $key])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('guest_passes', 1);

        $booking = Booking::factory()->create(['guest_id' => $guest->id, 'service_id' => $service->id, 'status' => 'completed']);
        app(BenefitsService::class)->completeBooking($booking);
        $this->post(route('admin.benefits.redeem', $guest), ['request_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertSame(0, app(BenefitsService::class)->loyaltyBalance($guest));
        $guestPass = GuestPass::firstOrFail();
        $this->post(route('admin.benefits.void', [$guest, $guestPass]), ['confirm' => 1])->assertSessionHasNoErrors();
        $this->assertNotNull($guestPass->fresh()->voided_at);
    }

    public function test_staff_cannot_manage_benefit_settings_or_guest_rewards(): void
    {
        $staff = Staff::factory()->create();
        $user = User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id]);
        $guest = Guest::factory()->create();
        $this->actingAs($user)->get(route('admin.benefits.index'))->assertForbidden();
        $this->post(route('admin.benefits.redeem', $guest), ['request_key' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_guest_benefit_history_is_visible_to_owner_but_not_staff(): void
    {
        $guest = Guest::factory()->create();
        $plan = PassPlan::factory()->create();
        app(BenefitsService::class)->issuePass($guest, $plan, $this->owner, (string) Str::uuid());
        $staff = Staff::factory()->create();
        Booking::factory()->create(['guest_id' => $guest->id, 'staff_id' => $staff->id]);

        $this->actingAs($this->owner)->get(route('admin.guest', $guest))->assertOk()->assertSee('Felhasználható alkalmak')->assertSee('HŰSÉGPONTOK');
        $staffUser = User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id]);
        $this->actingAs($staffUser)->get(route('admin.guest', $guest))->assertOk()->assertDontSee('Felhasználható alkalmak')->assertDontSee('HŰSÉGPONTOK');
    }

    public function test_admin_completion_applies_selected_pass_and_points_in_one_booking_transaction(): void
    {
        $guest = Guest::factory()->create();
        $plan = PassPlan::factory()->create();
        $pass = app(BenefitsService::class)->issuePass($guest, $plan, $this->owner, (string) Str::uuid());
        $start = CarbonImmutable::yesterday('Europe/Budapest')->setTime(10, 0)->utc();
        $booking = Booking::factory()->create(['guest_id' => $guest->id, 'service_id' => $plan->service_id, 'starts_at' => $start, 'ends_at' => $start->addHour(), 'busy_from' => $start, 'busy_until' => $start->addMinutes(75)]);

        $this->actingAs($this->owner)->post(route('admin.booking.update', $booking), ['action' => 'complete', 'revision' => $booking->fresh()->revision, 'guest_pass_id' => $pass->id])->assertSessionHasNoErrors();
        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame(4, $pass->fresh()->visits_remaining);
        $this->assertSame(1, app(BenefitsService::class)->loyaltyBalance($guest));

        $otherGuest = Guest::factory()->create();
        $wrongPass = app(BenefitsService::class)->issuePass($otherGuest, $plan, $this->owner, (string) Str::uuid());
        $invalidBooking = Booking::factory()->create(['guest_id' => $guest->id, 'service_id' => $plan->service_id, 'starts_at' => $start, 'ends_at' => $start->addHour(), 'busy_from' => $start, 'busy_until' => $start->addMinutes(75)]);
        $this->post(route('admin.booking.update', $invalidBooking), ['action' => 'complete', 'revision' => $invalidBooking->fresh()->revision, 'guest_pass_id' => $wrongPass->id])->assertSessionHasErrors('benefits');
        $this->assertSame('confirmed', $invalidBooking->fresh()->status);
        $this->assertSame(5, $wrongPass->fresh()->visits_remaining);
        $this->assertDatabaseMissing('loyalty_entries', ['booking_id' => $invalidBooking->id]);
    }
}
