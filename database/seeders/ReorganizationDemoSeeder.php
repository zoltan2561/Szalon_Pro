<?php

namespace Database\Seeders;

use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ReorganizationDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $database = str_replace('\\', '/', (string) config('database.connections.'.config('database.default').'.database'));
        if (! app()->environment('local') || ! str_contains($database, '/output/playwright/salon-workflows-20261030/') || ! Salon::query()->first()?->demo || config('mail.default') !== 'array') {
            throw new \LogicException('Reorganization demos require the isolated local QA database and disabled mail delivery.');
        }
        if (Service::where('name', 'QA átszervezés – rövid')->exists()) {
            throw new \LogicException('Preserve the existing QA scenario; do not seed it twice.');
        }
        $short = Service::factory()->create(['name' => 'QA átszervezés – rövid', 'duration_minutes' => 45, 'buffer_before' => 0, 'buffer_after' => 15, 'sort_order' => 999]);
        $long = Service::factory()->create(['name' => 'QA átszervezés – hosszú', 'duration_minutes' => 75, 'buffer_before' => 0, 'buffer_after' => 15, 'sort_order' => 999]);
        $firstStaff = $short->staff()->firstOrFail();
        $secondStaff = $long->staff()->firstOrFail();
        $firstStaff->update(['name' => 'QA Bori', 'sort_order' => 999]);
        $secondStaff->update(['name' => 'QA Ádám', 'sort_order' => 999]);
        $short->staff()->sync([$firstStaff->id, $secondStaff->id]);
        $long->staff()->sync([$firstStaff->id, $secondStaff->id]);
        $owner = User::where('role', 'owner')->firstOrFail();
        $day = CarbonImmutable::now(Salon::current()->timezone)->addDays(30)->next(CarbonImmutable::MONDAY)->startOfDay();
        $ids = [];
        foreach ([10, 11, 12] as $index => $hour) {
            $visit = app(BookingService::class)->create(['service_id' => $short->id, 'staff_id' => $firstStaff->id, 'starts_at' => $day->setTime($hour, 0)->toIso8601String(), 'name' => 'QA napi vendég '.($index + 1), 'email' => 'reorganization-'.Str::uuid().'@example.test', 'request_key' => Str::uuid()->toString()], $owner);
            $ids[] = $visit->id;
        }
        $partner = app(BookingService::class)->create(['service_id' => $long->id, 'staff_id' => $secondStaff->id, 'starts_at' => $day->addDay()->setTime(15, 0)->toIso8601String(), 'name' => 'QA cserepartner', 'email' => 'reorganization-partner-'.Str::uuid().'@example.test', 'request_key' => Str::uuid()->toString()], $owner);
        File::put(storage_path('app/private/reorganization-ui.json'), json_encode(['anchor_id' => $ids[0], 'later_ids' => array_slice($ids, 1), 'partner_id' => $partner->id, 'date' => $day->toDateString(), 'partner_date' => $day->addDay()->toDateString()], JSON_THROW_ON_ERROR));
        $this->command?->info('Isolated reorganization scenario prepared. No notifications sent.');
    }
}
