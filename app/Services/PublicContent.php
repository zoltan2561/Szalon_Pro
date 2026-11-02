<?php

namespace App\Services;

use App\Models\Salon;

class PublicContent
{
    /** @return array<string, array<string, string>> */
    public function groups(): array
    {
        return [
            'Menü és lábléc' => ['home_title' => 'Kezdőlap címe', 'nav_services' => 'Szolgáltatások menüpont', 'nav_about' => 'Bemutatkozás menüpont', 'nav_contact' => 'Kapcsolat menüpont', 'nav_booking' => 'Mobil foglalási menüpont', 'footer_tagline' => 'Lábléc rövid bemutatkozása', 'footer_privacy' => 'Adatkezelési hivatkozás', 'footer_login' => 'Munkatársi belépés hivatkozása', 'footer_credit' => 'Lábléc készítői felirata', 'mobile_cta' => 'Alsó foglalási sáv szövege'],
            'Nyitóoldal és bemutatkozás' => ['hero_services' => 'Szolgáltatások hivatkozás', 'hero_note' => 'Nyitóoldali rövid magyarázat', 'studio_eyebrow' => 'Bemutatkozás felső felirata', 'studio_heading' => 'Bemutatkozás címe', 'studio_text' => 'Bemutatkozás kiegészítő szövege', 'studio_booking' => 'Bemutatkozás foglalási hivatkozása'],
            'Szolgáltatások, szakemberek és galéria' => ['services_eyebrow' => 'Szolgáltatások felső felirata', 'services_heading' => 'Szolgáltatások címe', 'services_intro' => 'Szolgáltatások bevezetője', 'services_empty' => 'Üres szolgáltatáslista szövege', 'team_eyebrow' => 'Szakemberek felső felirata', 'team_heading' => 'Szakemberek címe', 'team_intro' => 'Szakemberek bevezetője', 'team_booking' => 'Szakember foglalási hivatkozása', 'gallery_eyebrow' => 'Galéria felső felirata', 'gallery_heading' => 'Galéria címe'],
            'Foglalás bemutatása' => ['journey_eyebrow' => 'Foglalás felső felirata', 'journey_heading' => 'Foglalás bemutatásának címe', 'journey_intro' => 'Foglalás bemutatásának bevezetője', 'journey_service' => 'Első lépés címe', 'journey_service_text' => 'Első lépés magyarázata', 'journey_time' => 'Második lépés címe', 'journey_time_text' => 'Második lépés magyarázata', 'journey_confirm' => 'Harmadik lépés címe', 'journey_confirm_text' => 'Harmadik lépés magyarázata', 'booking_title' => 'Foglalási oldal címe', 'booking_eyebrow' => 'Foglalási oldal felső felirata', 'booking_heading' => 'Foglalási oldal főcíme'],
            'Kapcsolat és nyitvatartás' => ['contact_eyebrow' => 'Kapcsolat felső felirata', 'contact_heading' => 'Kapcsolat címe', 'opening_heading' => 'Nyitvatartás címe', 'opening_closed' => 'Zárt nap felirata', 'contact_cta_heading' => 'Kapcsolat foglalási címe'],
            'Adatkezelési oldal' => ['privacy_title' => 'Adatkezelési oldal böngészőcíme', 'privacy_eyebrow' => 'Adatkezelési oldal felső felirata', 'privacy_heading' => 'Adatkezelési oldal főcíme', 'privacy_contact' => 'Adatkezelési kapcsolat felirata'],
        ];
    }

    /** @return array<string, string> */
    public function defaults(Salon $salon): array
    {
        $defaults = [
            'home_title' => 'Kezdőlap', 'nav_services' => 'Szolgáltatások', 'nav_about' => 'Rólunk', 'nav_contact' => 'Kapcsolat', 'nav_booking' => 'Időpontfoglalás',
            'footer_tagline' => $salon->tagline ?? '', 'footer_privacy' => 'Adatkezelés', 'footer_login' => 'Munkatársi belépés', 'footer_credit' => 'SzalonPRO', 'mobile_cta' => 'Egy kis idő magadra.',
            'hero_services' => 'Szolgáltatásaink', 'hero_note' => 'Szolgáltatás, szakember és szabad időpont egy helyen.',
            'studio_eyebrow' => 'JÓ HELYEN VAGY', 'studio_heading' => "Rólad szól.\nA részletekig.", 'studio_text' => $salon->about ?? '', 'studio_booking' => 'Találjuk meg a te időpontod',
            'services_eyebrow' => 'SZOLGÁLTATÁSAINK', 'services_heading' => 'Válaszd ki, ami neked szól.', 'services_intro' => 'Ár, időtartam és szabad helyek egyértelműen. Az időpontot te választod.', 'services_empty' => 'A szolgáltatások hamarosan elérhetők.',
            'team_eyebrow' => 'SZEMÉLYES FIGYELEM', 'team_heading' => 'Akik várnak rád', 'team_intro' => "Választhatsz szakembert is\naz időpontfoglalás során.", 'team_booking' => 'Hozzá foglalok',
            'gallery_eyebrow' => 'EGY PILLANTÁS HOZZÁNK', 'gallery_heading' => 'A szalon és a munkáink',
            'journey_eyebrow' => 'EGYSZERŰ IDŐPONTFOGLALÁS', 'journey_heading' => 'Néhány lépés, és várunk.', 'journey_intro' => 'A foglaláshoz nem kell vendégfiókot létrehoznod.',
            'journey_service' => 'Válassz szolgáltatást', 'journey_service_text' => 'Az árat és az időtartamot már a választás előtt látod.',
            'journey_time' => 'Találd meg az időpontod', 'journey_time_text' => 'Választhatsz szakembert, majd megnézheted a szabad helyeket.',
            'journey_confirm' => 'Véglegesítsd a foglalást', 'journey_confirm_text' => 'A visszaigazolás után a foglalás módosítható vagy lemondható a feltételek szerint.',
            'booking_title' => 'Időpontfoglalás', 'booking_eyebrow' => 'EGY LÉPÉSSEL KÖZELEBB', 'booking_heading' => 'A következő énidőd.',
            'contact_eyebrow' => 'TALÁLKOZZUNK', 'contact_heading' => 'Várunk szeretettel.', 'opening_heading' => 'Nyitvatartás', 'opening_closed' => 'Zárva', 'contact_cta_heading' => 'A következő énidőd.',
            'privacy_title' => 'Adatkezelés', 'privacy_eyebrow' => 'ADATKEZELÉS', 'privacy_heading' => 'Adatkezelési tájékoztató', 'privacy_contact' => 'Adatokkal kapcsolatos kérések:',
        ];

        return $salon->demo ? array_replace($defaults, config('branding.presets.'.$salon->theme.'.demo_copy', [])) : $defaults;
    }

    /** @return array<string, string> */
    public function forSalon(Salon $salon): array
    {
        $defaults = $this->defaults($salon);
        $overrides = array_filter(array_intersect_key($salon->content ?? [], $defaults), static fn (mixed $value): bool => is_string($value) && trim($value) !== '');

        return array_replace($defaults, $overrides);
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        $keys = array_keys(array_merge(...array_values($this->groups())));
        $rules = ['content' => 'sometimes|array:'.implode(',', $keys)];
        foreach ($keys as $key) {
            $rules['content.'.$key] = 'nullable|string|max:3000';
        }

        return $rules;
    }
}
