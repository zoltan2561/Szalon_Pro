<?php

namespace App\Http\Controllers;

use App\Models\Salon;
use App\Services\SmsGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModuleSettingsController extends Controller
{
    /** @var array<string, array{title: string, description: string}> */
    public const Modules = [
        'sharing' => ['title' => 'Megosztás és QR', 'description' => 'Foglalási linkek és letölthető QR-kódok, forrás szerinti összesítéssel.'],
        'waitlist' => ['title' => 'Várólista', 'description' => 'A felszabaduló időpontokra váró vendégek kezelése a munkafelületen.'],
        'sms' => ['title' => 'SMS-emlékeztetők', 'description' => 'Telefonos emlékeztetők a megadott számmal rendelkező vendégeknek. A tényleges küldéshez SMS-szolgáltató kapcsolata kell.'],
        'passes' => ['title' => 'Bérletek', 'description' => 'Szolgáltatáshoz kötött alkalmak és felhasználásuk kezelése.'],
        'loyalty' => ['title' => 'Hűségpontok', 'description' => 'Teljesített látogatások után pontgyűjtés és szabályozható beváltás.'],
        'financials' => ['title' => 'Pénzügyi összesítés', 'description' => 'A rögzített szolgáltatási összegek megjelenítése. Nem fizetés vagy könyvelés.'],
    ];

    public function index(SmsGateway $smsGateway): View
    {
        return view('admin.modules', ['modules' => self::Modules, 'smsConfigured' => $smsGateway->configured()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['features' => 'sometimes|array:'.implode(',', array_keys(self::Modules))]);
        DB::transaction(function () use ($request): void {
            $salon = Salon::whereKey(1)->lockForUpdate()->firstOrFail();
            $features = $salon->features ?? [];
            foreach (array_keys(self::Modules) as $key) {
                $features[$key] = $request->boolean('features.'.$key);
            }
            $salon->update(['features' => $features]);
        }, 5);

        return back()->with('success', 'A modulok beállításait elmentettük.');
    }
}
