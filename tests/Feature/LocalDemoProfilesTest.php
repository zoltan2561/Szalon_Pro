<?php

namespace Tests\Feature;

use App\Http\Controllers\ModuleSettingsController;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalDemoProfilesTest extends TestCase
{
    public const PreviewPassword = 'qa-only-local-demo-20261025';

    /** @return array<string, array{string}> */
    public static function profiles(): array
    {
        return ['salon' => ['salon'], 'barber' => ['barber'], 'nails' => ['nails']];
    }

    #[DataProvider('profiles')]
    public function test_each_profile_has_working_admin_with_crowded_calendar_and_optional_modules(string $profile): void
    {
        $path = storage_path('framework/qa2-'.$profile.'-'.Str::uuid().'.sqlite');
        touch($path);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $path]);
        DB::purge('sqlite');
        try {
            $this->assertSame(0, Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]));
            $salon = Salon::factory()->create(['name' => 'QA · '.['salon' => 'Fodrászat', 'barber' => 'Barber', 'nails' => 'Körmös'][$profile], 'theme' => $profile, 'demo' => true, 'features' => array_fill_keys(array_keys(ModuleSettingsController::Modules), true), 'billing_sending_enabled' => false, 'review_url' => 'https://g.page/r/qa-example/review']);
            $owner = User::factory()->create(['name' => 'QA Tulajdonos', 'role' => 'owner', 'email' => 'qa-owner@example.test', 'password' => self::PreviewPassword]);
            $service = Service::factory()->create(['name' => ['salon' => 'Hajvágás és formázás', 'barber' => 'Hajvágás és szakálligazítás', 'nails' => 'Géllakk és körömformázás'][$profile], 'duration_minutes' => 45, 'buffer_after' => 15]);
            $staff = $service->staff()->first();
            $staff->update(['name' => 'Fodor Annamária']);
            $staffList = collect([$staff, Staff::factory()->create(['name' => 'Kiss Boglárka']), Staff::factory()->create(['name' => 'Nagy Eszter'])]);
            $service->staff()->sync($staffList->pluck('id')->all());
            $day = CarbonImmutable::now('Europe/Budapest')->startOfWeek();
            foreach ($staffList as $personIndex => $person) {
                foreach (range(9, 17) as $hour) {
                    $start = $day->setTime($hour, 0)->utc();
                    Booking::factory()->create(['guest_id' => Guest::factory()->create(['name' => $hour === 12 ? 'Hosszú Nevű Szintetikus Bemutató Vendég' : 'Minta Vendég '.$personIndex.'-'.$hour, 'email' => 'qa-'.$personIndex.'-'.$hour.'@example.test'])->id,
                        'service_id' => $service->id, 'service_name' => $service->name, 'staff_id' => $person->id, 'starts_at' => $start, 'ends_at' => $start->addMinutes(45), 'busy_from' => $start, 'busy_until' => $start->addHour(), 'status' => ['confirmed', 'completed', 'cancelled', 'no_show'][$hour % 4], 'actual_amount' => 9500, 'revision' => 1]);
                }
            }
            foreach (['admin.today', 'admin.guests', 'admin.notifications', 'admin.sharing.index', 'admin.vouchers.index', 'admin.statistics.index', 'admin.billing.index', 'admin.modules.index'] as $route) {
                $this->actingAs($owner)->get(route($route))->assertOk();
            }
            $this->get(route('admin.modules.index'))->assertOk()->assertSee('name="contact_mode"', false);
            $this->get(route('admin.booking', Booking::where('status', 'completed')->firstOrFail()))->assertOk()->assertSee('Nyugta vagy számla')->assertSee('Ajándékutalvány beváltása');
            $this->get(route('admin.calendar', ['date' => $day->toDateString()]))->assertOk()->assertSee('Hosszú Nevű Szintetikus Bemutató Vendég')->assertSee('calendar-agenda-item', false);
            $this->get(route('admin.calendar', ['date' => $day->toDateString(), 'view' => 'week']))->assertOk()->assertSee('calendar-week-booking', false);
            $this->assertSame(27, Booking::count());
            if (getenv('SALON_QA_OUTPUT')) {
                $directory = getenv('SALON_QA_OUTPUT');
                File::ensureDirectoryExists($directory);
                $manifestPath = $directory.'/profiles.json';
                $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR) : [];
                $manifest[$profile] = ['database' => $path, 'date' => $day->toDateString(), 'email' => $owner->email, 'bookings' => 27];
                File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        } finally {
            DB::disconnect('sqlite');
            if (! getenv('SALON_QA_OUTPUT')) {
                File::delete($path);
            }
        }
    }
}
