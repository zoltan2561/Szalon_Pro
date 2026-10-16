<?php

namespace App\Console\Commands;

use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\Availability;
use App\Services\BookingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstallSalon extends Command
{
    protected $signature = 'salon:install {--demo= : salon vagy barber; kizárólag üres demó-adatbázisban} {--email= : Tulajdonos e-mail-címe}';

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
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $password || strlen($password) < 12 || ($demo && ! in_array($demo, ['salon', 'barber']))) {
            $this->error('Érvényes --email és legalább 12 karakteres SALON_ADMIN_PASSWORD szükséges. A --demo értéke salon vagy barber lehet.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($password, $email, $demo, $bookings, $availability): void {
            $hours = [1 => [['09:00', '18:00']], 2 => [['09:00', '18:00']], 3 => [['09:00', '18:00']], 4 => [['09:00', '18:00']], 5 => [['09:00', '18:00']], 6 => [['09:00', '14:00']], 7 => []];
            $salon = Salon::create(['id' => 1, 'name' => $demo === 'barber' ? 'Minta Barber' : 'Minta Szalon', 'tagline' => $demo === 'barber' ? 'Karakter. Stílus. A te időd.' : 'Egy kis idő. Csak neked.', 'about' => 'Személyes figyelem, átlátható szolgáltatások és időpontok, amelyek hozzád igazodnak. Válassz szolgáltatást, és találd meg a következő szabad időpontod.', 'phone' => '+36 00 000 0000', 'email' => $email, 'address' => 'Bemutató helyszín – nem valódi szalon', 'theme' => $demo === 'barber' ? 'barber' : 'salon', 'accent' => $demo === 'barber' ? '#c2ae78' : '#765b55', 'font' => $demo === 'barber' ? 'sans' : 'serif', 'opening_hours' => $hours, 'demo' => (bool) $demo, 'terms' => 'Online lemondás és áthelyezés az időpont előtt legalább 24 órával lehetséges. Ezt követően kérjük, keress telefonon.', 'privacy' => 'BEMUTATÓ TÁJÉKOZTATÓ – ÉLES HASZNÁLATRA NEM ALKALMAS. A rendszer a foglalás teljesítéséhez nevet, e-mail-címet és opcionálisan telefonszámot kezel. Ne adj meg valódi személyes adatot a bemutatóban. Élesítés előtt a szolgáltatónak a saját működésére szabott adatkezelési tájékoztatót kell megadnia.']);
            $owner = User::create(['name' => 'Tulajdonos', 'email' => $email, 'password' => $password, 'role' => 'owner', 'can_manage_bookings' => true, 'can_view_notes' => true]);
            if (! $demo) {
                $salon->update(['name' => 'Saját szalonom', 'address' => null, 'demo' => false, 'opening_hours' => []]);

                return;
            }
            $a = Staff::create(['name' => 'Minta Anna', 'title' => $demo === 'barber' ? 'Barber · demó szakember' : 'Fodrász · demó szakember', 'bio' => 'Szintetikus bemutató profil. Éles telepítésnél a saját szakembereid adatai jelennek meg.', 'weekly_hours' => $hours]);
            $b = Staff::create(['name' => 'Minta Dóra', 'title' => $demo === 'barber' ? 'Barber · demó szakember' : 'Körmös · demó szakember', 'bio' => 'Szintetikus bemutató profil. A munkaidő és a foglalható szolgáltatások külön állíthatók.', 'weekly_hours' => $hours]);
            $catalog = $demo === 'barber' ? [['Hajvágás', 'Barber', 30, 6500], ['Hajvágás és szakáll', 'Barber', 60, 9900], ['Szakálligazítás', 'Barber', 20, 3900]] : [['Hajvágás és szárítás', 'Hajápolás', 60, 9500], ['Géllakk és manikűr', 'Kézápolás', 90, 8500], ['Alkalmi frizura', 'Hajformázás', 75, 14500]];
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
