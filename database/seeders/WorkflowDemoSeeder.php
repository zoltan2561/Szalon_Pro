<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WaitlistOffer;
use App\Services\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class WorkflowDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $database = str_replace('\\', '/', (string) config('database.connections.'.config('database.default').'.database'));
        if (! app()->environment('local', 'testing') || ! Salon::query()->first()?->demo || (app()->environment('local') && ! str_contains($database, '/output/playwright/salon-workflows-20261030/'))) {
            throw new \LogicException('Workflow demos require an isolated local demo fixture.');
        }
        $salon = Salon::current();
        $salon->update(['features' => array_replace($salon->features ?? [], ['waitlist' => true, 'waitlist_auto' => false])]);
        $service = Service::where('active', true)->with('staff')->orderBy('sort_order')->firstOrFail();
        $staff = $service->staff->where('active', true)->firstOrFail();
        User::updateOrCreate(['email' => 'worker@workflow.example.test'], ['name' => 'Bemutató munkatárs', 'role' => 'staff', 'staff_id' => $staff->id, 'can_manage_bookings' => true, 'can_view_notes' => true, 'password' => config('salon.admin_password')]);
        $today = CarbonImmutable::now($salon->timezone)->startOfDay();
        $next = app(Availability::class)->firstAvailableDay($service, $today->addDay()->toImmutable(), $today->addDays(7)->toImmutable(), $staff->id);
        if (! $next) {
            throw new \LogicException('The local demo needs an available appointment.');
        }
        $guest = Guest::firstOrCreate(['email' => 'workflow-waiter@example.test'], ['name' => 'Bemutató várakozó vendég']);
        $entryToken = bin2hex(random_bytes(32));
        $entry = WaitlistEntry::updateOrCreate(['guest_id' => $guest->id, 'service_id' => $service->id, 'status' => 'waiting'], ['staff_id' => $staff->id, 'preferred_date' => $next['date'], 'preferred_until' => $next['date'], 'notification_consent_at' => now(), 'token_hash' => hash('sha256', $entryToken), 'manage_token' => $entryToken, 'token_expires_at' => now()->addWeek()]);
        $offer = WaitlistOffer::where('waitlist_entry_id', $entry->id)->where('status', 'pending')->first();
        if ($offer) {
            $offer->update(['starts_at' => $next['slots'][0]['starts_at'], 'expires_at' => now()->addHours(4)]);
        } else {
            $offer = WaitlistOffer::factory()->create(['waitlist_entry_id' => $entry->id, 'staff_id' => $staff->id, 'starts_at' => $next['slots'][0]['starts_at'], 'expires_at' => now()->addHours(4)]);
        }
        $visit = Booking::where('staff_id', $staff->id)->where('status', 'confirmed')->where('starts_at', '>=', $today->utc())->where('starts_at', '<', $today->addDay()->utc())->first();
        $extra = Service::where('active', true)->where('id', '!=', $service->id)->whereHas('staff', fn ($query) => $query->where('staff.id', $staff->id))->first();
        File::put(storage_path('app/private/workflow-ui-links.json'), json_encode(['offer_url' => route('waitlist.offer', $offer->manage_token), 'staff_id' => $staff->id, 'service_id' => $service->id, 'extra_id' => $extra?->id, 'visit_id' => $visit?->id, 'series_date' => $today->addDays(max(1, $salon->horizon_days - 15))->startOfWeek()->toDateString(), 'leave_date' => $today->addDays($salon->horizon_days + 14)->toDateString()], JSON_THROW_ON_ERROR));
        $this->command?->info('Isolated workflow demo prepared. No notifications sent.');
    }
}
