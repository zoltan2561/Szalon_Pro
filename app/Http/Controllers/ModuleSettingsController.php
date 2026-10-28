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
        'billing' => ['title' => 'Számlázz.hu bizonylatok', 'description' => 'Nyugta és számla. Szolgáltatói és könyvelői beállítást igényel.'],
        'statistics' => ['title' => 'Statisztika', 'description' => 'Foglalások és kihasználtság áttekintése.'],
        'vouchers' => ['title' => 'Ajándékutalványok', 'description' => 'Utalványok kiadása és beváltása, online fizetés nélkül.'],
        'reviews' => ['title' => 'Értékelés kérése', 'description' => 'Google-értékelés kérése a vendég hozzájárulásával.'],
        'phone_booking' => ['title' => 'Telefonközpontú foglalás', 'description' => 'A kötelező vendégelérhetőség kiválasztása.'],
        'no_show_protection' => ['title' => 'Meg nem jelenés elleni védelem', 'description' => 'Korlátozás ismételt meg nem jelenés után.'],
        'sharing' => ['title' => 'Megosztás és QR', 'description' => 'Megosztható foglalási linkek és QR-kódok.'],
        'waitlist' => ['title' => 'Várólista', 'description' => 'Vendégek kezelése felszabaduló időpontokhoz.'],
        'sms' => ['title' => 'SMS-emlékeztetők', 'description' => 'Emlékeztetők küldése SMS-szolgáltatón keresztül.'],
        'passes' => ['title' => 'Bérletek', 'description' => 'Bérletalkalmak kiadása és felhasználása.'],
        'loyalty' => ['title' => 'Hűségpontok', 'description' => 'Pontgyűjtés és beváltás látogatások után.'],
        'financials' => ['title' => 'Pénzügyi összesítés', 'description' => 'Rögzített összegek áttekintése; nem könyvelés.'],
    ];

    /** @var array<string, list<string>> */
    public const ModuleGroups = [
        'Foglalás és vendégek' => ['phone_booking', 'no_show_protection', 'waitlist', 'sms'],
        'Pénzügy és kedvezmények' => ['billing', 'financials', 'vouchers', 'passes', 'loyalty'],
        'Marketing és elemzés' => ['reviews', 'sharing', 'statistics'],
    ];

    public function index(SmsGateway $smsGateway): View
    {
        return view('admin.modules', ['modules' => self::Modules, 'moduleGroups' => self::ModuleGroups, 'smsConfigured' => $smsGateway->configured()]);
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
