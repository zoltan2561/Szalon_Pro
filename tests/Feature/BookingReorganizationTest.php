<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\OutboxMessage;
use App\Models\Salon;
use App\Models\Service;
use App\Models\TimeBlock;
use App\Models\User;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingReorganizationTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19T06:00:00Z'));
        Salon::factory()->create();
        $this->service = Service::factory()->create(['duration_minutes' => 45, 'buffer_before' => 0, 'buffer_after' => 15]);
        $this->owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($this->owner);
    }

    private function visit(string $start, ?Service $service = null, ?int $staffId = null): Booking
    {
        $service ??= $this->service;

        return app(BookingService::class)->create(['service_id' => $service->id, 'staff_id' => $staffId ?? $service->staff->first()->id, 'starts_at' => $start, 'name' => 'Átszervezés teszt', 'email' => Str::uuid().'@example.test', 'request_key' => Str::uuid()->toString()]);
    }

    private function preview(Booking $booking, array $input): string
    {
        return $this->post(route('admin.booking.reorganize.preview', $booking), ['revision' => $booking->revision] + $input)->assertOk()->assertViewHas('plan', fn (array $plan): bool => $plan['available'])->viewData('planToken');
    }

    public function test_day_shift_is_previewed_then_moves_all_later_visits_atomically_and_queues_changes(): void
    {
        $first = $this->visit('2026-10-19T10:00:00+02:00');
        $second = $this->visit('2026-10-19T11:00:00+02:00');
        $third = $this->visit('2026-10-19T12:00:00+02:00');
        $tomorrow = $this->visit('2026-10-20T10:00:00+02:00');
        $token = $this->preview($first, ['mode' => 'shift_day', 'shift_minutes' => 60]);
        $this->assertSame(1, $first->fresh()->revision);
        $this->post(route('admin.booking.reorganize.commit', $first), ['plan_token' => $token])->assertSessionHasNoErrors()->assertRedirect(route('admin.booking', $first));
        $this->assertSame(['11:00', '12:00', '13:00'], collect([$first, $second, $third])->map(fn (Booking $visit): string => $visit->fresh()->starts_at->setTimezone('Europe/Budapest')->format('H:i'))->all());
        $this->assertSame(1, $tomorrow->fresh()->revision);
        $this->assertSame(3, OutboxMessage::where('kind', 'changed')->where('revision', 2)->count());
        $this->post(route('admin.booking.reorganize.commit', $first), ['plan_token' => $token])->assertSessionHasErrors('reorganization');
        $this->assertSame(2, $first->fresh()->revision);
    }

    public function test_negative_shift_preserves_earlier_and_other_staff_visits(): void
    {
        $earlier = $this->visit('2026-10-19T10:00:00+02:00');
        $first = $this->visit('2026-10-19T12:00:00+02:00');
        $second = $this->visit('2026-10-19T13:00:00+02:00');
        $otherService = Service::factory()->create();
        $other = $this->visit('2026-10-19T12:00:00+02:00', $otherService);
        $token = $this->preview($first, ['mode' => 'shift_day', 'shift_minutes' => -60]);
        $this->post(route('admin.booking.reorganize.commit', $first), ['plan_token' => $token])->assertSessionHasNoErrors();
        $this->assertSame('11:00', $first->fresh()->starts_at->setTimezone('Europe/Budapest')->format('H:i'));
        $this->assertSame('12:00', $second->fresh()->starts_at->setTimezone('Europe/Budapest')->format('H:i'));
        $this->assertSame(1, $earlier->fresh()->revision);
        $this->assertSame(1, $other->fresh()->revision);
    }

    public function test_swap_keeps_each_service_and_duration_and_can_cross_days(): void
    {
        $first = $this->visit('2026-10-19T10:00:00+02:00');
        $long = Service::factory()->create(['duration_minutes' => 75]);
        $long->staff()->sync([$first->staff_id]);
        $second = $this->visit('2026-10-20T13:00:00+02:00', $long, $first->staff_id);
        $token = $this->preview($first, ['mode' => 'swap', 'swap_booking_id' => $second->id]);
        $this->post(route('admin.booking.reorganize.commit', $first), ['plan_token' => $token])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-20 13:00', $first->fresh()->starts_at->setTimezone('Europe/Budapest')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-19 10:00', $second->fresh()->starts_at->setTimezone('Europe/Budapest')->format('Y-m-d H:i'));
        $this->assertSame(45, $first->fresh()->duration_minutes);
        $this->assertSame(75, $second->fresh()->duration_minutes);
        $this->assertSame($long->id, $second->fresh()->service_id);
    }

    public function test_swap_checks_internal_overlap_and_staff_qualifications(): void
    {
        $first = $this->visit('2026-10-19T10:00:00+02:00');
        $long = Service::factory()->create(['duration_minutes' => 75]);
        $long->staff()->sync([$first->staff_id]);
        $second = $this->visit('2026-10-19T11:00:00+02:00', $long, $first->staff_id);
        $this->post(route('admin.booking.reorganize.preview', $first), ['revision' => 1, 'mode' => 'swap', 'swap_booking_id' => $second->id])->assertOk()->assertViewHas('planToken', null)
            ->assertViewHas('plan', fn (array $plan): bool => ! $plan['available'] && count($plan['rows'][0]['conflicts']) > 0);
        $unqualified = $this->visit('2026-10-20T10:00:00+02:00', Service::factory()->create());
        $this->post(route('admin.booking.reorganize.preview', $first), ['revision' => 1, 'mode' => 'swap', 'swap_booking_id' => $unqualified->id])->assertOk()->assertViewHas('planToken', null);
        $this->assertSame(1, $first->fresh()->revision);
    }

    public function test_owner_can_swap_qualified_staff_but_read_only_staff_cannot_plan_changes(): void
    {
        $first = $this->visit('2026-10-19T10:00:00+02:00');
        $otherService = Service::factory()->create();
        $second = $this->visit('2026-10-19T13:00:00+02:00', $otherService);
        $this->service->staff()->syncWithoutDetaching([$second->staff_id]);
        $otherService->staff()->syncWithoutDetaching([$first->staff_id]);
        $token = $this->preview($first, ['mode' => 'swap', 'swap_booking_id' => $second->id]);
        $firstStaff = $first->staff_id;
        $this->post(route('admin.booking.reorganize.commit', $first), ['plan_token' => $token])->assertSessionHasNoErrors();
        $this->assertSame($second->staff_id, $first->fresh()->staff_id);
        $this->assertSame($firstStaff, $second->fresh()->staff_id);
        $reader = User::factory()->create(['role' => 'staff', 'staff_id' => $firstStaff, 'can_manage_bookings' => false]);
        $this->actingAs($reader)->get(route('admin.booking.reorganize.options', ['booking' => $second, 'date' => '2026-10-19']))->assertForbidden();
        $this->post(route('admin.booking.reorganize.preview', $second), ['revision' => 2, 'mode' => 'shift_day', 'shift_minutes' => 60])->assertForbidden();
    }

    public function test_new_block_after_preview_prevents_all_changes(): void
    {
        $first = $this->visit('2026-10-19T10:00:00+02:00');
        $second = $this->visit('2026-10-19T11:00:00+02:00');
        $token = $this->preview($first, ['mode' => 'shift_day', 'shift_minutes' => 60]);
        TimeBlock::factory()->create(['staff_id' => $first->staff_id, 'starts_at' => CarbonImmutable::parse('2026-10-19T12:00:00+02:00')->utc(), 'ends_at' => CarbonImmutable::parse('2026-10-19T13:00:00+02:00')->utc()]);
        $this->post(route('admin.booking.reorganize.commit', $first), ['plan_token' => $token])->assertSessionHasErrors('reorganization');
        $this->assertSame(1, $first->fresh()->revision);
        $this->assertSame(1, $second->fresh()->revision);
        $this->assertSame(0, OutboxMessage::where('kind', 'changed')->count());
    }

    public function test_new_visit_or_changed_partner_after_preview_requires_a_new_preview(): void
    {
        $first = $this->visit('2026-10-19T10:00:00+02:00');
        $second = $this->visit('2026-10-19T12:00:00+02:00');
        $token = $this->preview($first, ['mode' => 'shift_day', 'shift_minutes' => 60]);
        $this->visit('2026-10-19T15:00:00+02:00');
        $this->post(route('admin.booking.reorganize.commit', $first), ['plan_token' => $token])->assertSessionHasErrors('reorganization');
        $swap = $this->preview($first, ['mode' => 'swap', 'swap_booking_id' => $second->id]);
        app(BookingService::class)->change($second, ['action' => 'move', 'revision' => 1, 'staff_id' => $second->staff_id, 'starts_at' => '2026-10-19T14:00:00+02:00'], $this->owner);
        $this->post(route('admin.booking.reorganize.commit', $first), ['plan_token' => $swap])->assertSessionHasErrors('reorganization');
        $this->assertSame(1, $first->fresh()->revision);
        $this->assertSame('14:00', $second->fresh()->starts_at->setTimezone('Europe/Budapest')->format('H:i'));
    }

    public function test_staff_scope_tokens_and_expiry_are_enforced(): void
    {
        $first = $this->visit('2026-10-19T10:00:00+02:00');
        $other = $this->visit('2026-10-19T13:00:00+02:00', Service::factory()->create());
        $token = $this->preview($first, ['mode' => 'shift_day', 'shift_minutes' => 60]);
        $staff = User::factory()->create(['role' => 'staff', 'staff_id' => $first->staff_id, 'can_manage_bookings' => true]);
        $this->actingAs($staff)->get(route('admin.booking.reorganize.options', ['booking' => $first, 'date' => '2026-10-19']))->assertOk()->assertJsonCount(0, 'options');
        $this->post(route('admin.booking.reorganize.preview', $other), ['revision' => 1, 'mode' => 'shift_day', 'shift_minutes' => 60])->assertForbidden();
        $this->post(route('admin.booking.reorganize.preview', $first), ['revision' => 1, 'mode' => 'swap', 'swap_booking_id' => $other->id])->assertForbidden();
        $this->post(route('admin.booking.reorganize.commit', $first), ['plan_token' => $token])->assertSessionHasErrors('reorganization');
        $this->actingAs($this->owner)->post(route('admin.booking.reorganize.commit', $first), ['plan_token' => 'invalid-token'])->assertSessionHasErrors('reorganization');
        $this->travel(11)->minutes();
        $this->post(route('admin.booking.reorganize.commit', $first), ['plan_token' => $token])->assertSessionHasErrors('reorganization');
        $this->assertSame(1, $first->fresh()->revision);
    }

    public function test_invalid_offsets_and_work_hours_are_not_silently_overridden(): void
    {
        $first = $this->visit('2026-10-19T17:00:00+02:00');
        foreach ([0, 7, 721] as $minutes) {
            $this->post(route('admin.booking.reorganize.preview', $first), ['revision' => 1, 'mode' => 'shift_day', 'shift_minutes' => $minutes])->assertSessionHasErrors();
        }
        $this->post(route('admin.booking.reorganize.preview', $first), ['revision' => 1, 'mode' => 'shift_day', 'shift_minutes' => 60])->assertOk()->assertViewHas('planToken', null);
        $this->post(route('admin.booking.reorganize.preview', $first), ['revision' => 1, 'mode' => 'shift_day', 'shift_minutes' => 720])->assertOk()->assertViewHas('planToken', null)->assertSee('Az eltolás átlépné a nap elejét vagy végét.');
        $this->assertSame(1, $first->fresh()->revision);
    }

    public function test_history_formats_winter_and_summer_changes_in_salon_local_time(): void
    {
        foreach (['2026-10-19' => '+02:00', '2026-10-30' => '+01:00'] as $day => $offset) {
            $booking = $this->visit($day.'T10:00:00'.$offset);
            app(BookingService::class)->change($booking, ['action' => 'move', 'revision' => 1, 'staff_id' => $booking->staff_id, 'starts_at' => $day.'T11:00:00'.$offset], $this->owner);
            $this->get(route('admin.booking', $booking))->assertOk()->assertSee(str_replace('-', '. ', $day).'. 10:00')->assertSee(str_replace('-', '. ', $day).'. 11:00')->assertDontSee('(UTC)')->assertSee('Korábban')->assertSee('Új időpont');
        }
    }
}
