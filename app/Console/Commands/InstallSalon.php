<?php

namespace App\Console\Commands;

use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\Availability;
use App\Services\BookingService;
use App\Services\Branding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstallSalon extends Command
{
    protected $signature = 'salon:install {--demo= : salon, barber vagy nails; kizárólag üres demó-adatbázisban} {--email= : Tulajdonos e-mail-címe}';

    protected $description = 'Üres telepítés inicializálása tulajdonosi hozzáféréssel; a jelszót a SALON_ADMIN_PASSWORD változó adja';

    public function handle(BookingService $bookings, Availability $availability): int
    {
        if (Salon::exists() || User::exists()) {
            $this->error('A telepítés már tartalmaz adatot; nem írtunk felül semmit.');

            return self::FAILURE;
        }
        $password = env('SALON_ADMIN_PASSWORD');
        $email = $this->option('email');
        $demo = $this->option('demo');
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $password || strlen($password) < 12 || ($demo && ! in_array($demo, ['salon', 'barber', 'nails']))) {
            $this->error('Érvényes --email és legalább 12 karakteres SALON_ADMIN_PASSWORD szükséges. A --demo értéke salon, barber vagy nails lehet.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($password, $email, $demo, $bookings, $availability): void {
            $hours = [1 => [['09:00', '18:00']], 2 => [['09:00', '18:00']], 3 => [['09:00', '18:00']], 4 => [['09:00', '18:00']], 5 => [['09:00', '18:00']], 6 => [['09:00', '14:00']], 7 => []];
            $preset = app(Branding::class)->preset($demo ?: 'salon');
            $salon = Salon::create(['id' => 1, 'name' => $preset['name'], 'tagline' => $preset['tagline'], 'about' => $preset['about'], 'phone' => '+36 00 000 0000', 'email' => $email, 'address' => 'Bemutató helyszín – nem valódi szalon', 'theme' => $demo ?: 'salon', 'accent' => $preset['accent'], 'font' => $preset['font'], 'appearance' => $preset['appearance'], 'opening_hours' => $hours, 'demo' => (bool) $demo, 'terms' => 'Online lemondás és áthelyezés az időpont előtt legalább 24 órával lehetséges. Ezt követően kérjük, keress telefonon.', 'privacy' => 'BEMUTATÓ TÁJÉKOZTATÓ – ÉLES HASZNÁLATRA NEM ALKALMAS. A rendszer a foglalás teljesítéséhez nevet, e-mail-címet és opcionálisan telefonszámot kezel. Ne adj meg valódi személyes adatot a bemutatóban. Élesítés előtt a szolgáltatónak a saját működésére szabott adatkezelési tájékoztatót kell megadnia.']);
            $owner = User::create(['name' => 'Tulajdonos', 'email' => $email, 'password' => $password, 'role' => 'owner', 'can_manage_bookings' => true, 'can_view_notes' => true]);
            if (! $demo) {
                $salon->update(['name' => 'Saját szalonom', 'address' => null, 'demo' => false, 'opening_hours' => []]);

                return;
            }
            $title = match ($demo) {
                'barber' => 'Barber', 'nails' => 'Kézápoló és műkörömépítő', default => 'Fodrász'
            };
            $a = Staff::create(['name' => 'Minta Anna', 'title' => $title.' · demó szakember', 'bio' => 'Személyes figyelem, gondos részletek. Bemutató szakember.', 'weekly_hours' => $hours]);
            $b = Staff::create(['name' => 'Minta Dóra', 'title' => $title.' · demó szakember', 'bio' => 'Találjuk meg együtt a hozzád illő stílust. Bemutató szakember.', 'weekly_hours' => $hours]);
            $catalog = match ($demo) {
                'barber' => [['Hajvágás', 'Barber', 30, 6500], ['Hajvágás és szakáll', 'Barber', 60, 9900], ['Szakálligazítás', 'Barber', 20, 3900]],
                'nails' => [['Géllakk és manikűr', 'Kézápolás', 60, 8500], ['Műkörömépítés', 'Műköröm', 120, 12500], ['Műköröm töltés', 'Műköröm', 90, 10500]],
                default => [['Női hajvágás és szárítás', 'Hajápolás', 60, 12000], ['Férfi hajvágás', 'Hajápolás', 30, 6500], ['Alkalmi frizura', 'Hajformázás', 75, 14500]],
            };
            foreach ($catalog as $i => $row) {
                $service = Service::create(['name' => $row[0], 'category' => $row[1], 'description' => 'Bemutató szolgáltatás, szabadon módosítható tartalommal és időtartammal.', 'duration_minutes' => $row[2], 'price' => $row[3], 'price_kind' => $i === 2 ? 'from' : 'fixed', 'buffer_after' => 15]);
                $service->staff()->attach($i === 1 ? $b->id : $a->id);
            }
            $salon->update(['published' => true]);
            $service = Service::first();
            $date = now($salon->timezone)->toDateString();
            $slots = $availability->slots($service, $date, $a->id);
            foreach (array_slice($slots, 0, 1) as $slot) {
                $bookings->create(['service_id' => $service->id, 'staff_id' => $slot['staff_id'], 'starts_at' => $slot['starts_at'], 'name' => 'Minta Vendég', 'email' => 'guest@example.test', 'phone' => null, 'request_key' => Str::uuid()->toString()], $owner);
            }
        });
        $this->info('A telepítés elkészült. Belépés a /belepes oldalon, a megadott e-maillel és a környezeti változóban megadott jelszóval.');

        return self::SUCCESS;
    }
}
