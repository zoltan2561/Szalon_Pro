<?php

namespace App\Services;

use App\Models\Salon;

class DemoJourney
{
    public const ProductUrl = 'https://szoftlab.hu/termekek/szalonpro';

    public function contact(string $placement, string $type = 'order'): string
    {
        $source = request()->getHost();
        if (! in_array($source, ['szalon.pro', 'barber.szalon.pro', 'nails.szalon.pro'], true)) {
            $source = ['salon' => 'szalon.pro', 'barber' => 'barber.szalon.pro', 'nails' => 'nails.szalon.pro'][Salon::current()->theme] ?? 'szalon.pro';
        }

        return 'https://szoftlab.hu/kapcsolat?'.http_build_query([
            'tipus' => $type === 'question' ? 'question' : 'order', 'termek' => 'szalonpro', 'csomag' => 'monthly',
            'utm_source' => $source, 'utm_medium' => 'demo', 'utm_campaign' => $placement,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array<string, string> */
    public function faq(): array
    {
        return [
            'Mennyibe kerül, és mit jelent az ÁFA-mentes ár?' => '4 900 Ft / hó, bevezetési díj nélkül. ÁFA-mentes ár (alanyi adómentes).',
            'Van hűségidő?' => 'Nincs hűségidő, 30 napos felmondással bármikor lemondható. A szerződés annak a hónapnak a végén szűnik meg, amelyben a 30 napos felmondási idő lejár; a részletes feltételeket az ÁSZF tartalmazza.',
            'Hogyan működik a Google Naptár kapcsolata?' => 'A rendszer a foglalásokat a kapcsolt Google Naptárba küldi, az onnan beolvasott foglaltságot pedig figyelembe veszi az időpontválasztásnál. A használathoz Google Naptár-kapcsolat beállítása szükséges.',
            'Hol lesz a saját foglalóoldalam?' => 'Saját aldomaint kapsz, például a szalonod.szalon.pro címet. A szolgáltatás bevezetési díj nélkül indul.',
            'Ki tölti fel a szolgáltatásokat és a munkarendet?' => 'A saját szolgáltatásaidat, áraikat, munkatársaidat és nyitvatartásodat te töltöd fel a saját felületeden.',
        ];
    }

    public function indexable(): bool
    {
        return Salon::current()->demo && config('salon.demo_hub')
            && Salon::current()->theme === 'salon'
            && request()->getHost() === 'szalon.pro' && request()->routeIs('home') && ! request()->query();
    }
}
