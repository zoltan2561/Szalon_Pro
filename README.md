# SzalonPRO

Saját arculatra szabható Laravel 13 szalonweboldal és napi szalonkezelés, a `p-szalon_pro` Slack-csatorna 2026. október 14-i, hatrészes MVP-terve alapján.

## Kipróbálás ebben a munkakönyvtárban

- Szalon: http://127.0.0.1:8095
- Barber: http://127.0.0.1:8096
- Műköröm: http://127.0.0.1:8097
- Munkafelület: az adott címen `/admin`; belépés: `/belepes`.
- Szalon belépési e-mail: `owner@example.test`; barber: `barber@example.test`; műköröm: `nails@example.test`.
- A helyi, véletlen jelszó a figyelmen kívül hagyott `.env`, `.env.barber`, illetve `.env.nails` fájl `SALON_ADMIN_PASSWORD` sorában található. Nincs a forráskódban vagy ebben a dokumentumban.
- Külön adatbázis: `database/database.sqlite`, `database/barber.sqlite`, `database/nails.sqlite`; külön munkamenetsüti. A nevek, profilok és foglalások szintetikus demóadatok. Az adatbázisok, feltöltések és titkok nincsenek Gitben.
- A nyilvános demóváltó a három külön telepítés között navigál. A `.env`, `.env.barber` és `.env.nails` fájlban a `SALON_DEMO_SALON_URL`, `SALON_DEMO_BARBER_URL`, `SALON_DEMO_NAILS_URL` címek legyenek megadva; éles ügyféloldalon a váltó nem jelenik meg. A három szakágnak külön szolgáltatáslistája, munkatársai és foglalásai vannak.
- A demó termékérdeklődési gombja a `SALON_DEMO_SALES_CONTACT` mezőben beállított valódi e-mail-címre vagy HTTPS-címre vezet. Üres értéknél nem jelenik meg; a mintaszalon fiktív kapcsolatát nem használjuk ajánlatkérésre.

Újraindítás külön PowerShell-terminálokban:

```powershell
php artisan serve --host=127.0.0.1 --port=8095 --no-reload
```

```powershell
php artisan serve --env=barber --host=127.0.0.1 --port=8096 --no-reload
```

```powershell
php artisan serve --env=nails --host=127.0.0.1 --port=8097 --no-reload
```

A felület Blade és közvetlenül kiszolgált CSS/JavaScript; használatához nem szükséges Node vagy frontend-build.

## Funkciók

- Három mentett, tovább alakítható szakági arculat: barber, fodrászat, műköröm. A bemutatók főoldalán a szakaszok sorrendje és felépítése, a tipográfia, a szolgáltatások megjelenése és a szövegezés is eltér. A Beállításokban egy kattintással alkalmazható egy arculat, a vállalkozás adatai, feltöltött képei, szolgáltatásai és foglalásai megmaradnak.
- Saját generált SP-logó, szakági enteriőrképek, mobil/asztali WebP változatok, helyből betöltött DM Sans és Playfair Display betűk, Phosphor ikonok (licencek az eszközök mellett).
- Nagy képes vagy osztott főoldal; színek, betűk, lekerekítés, főoldali feliratok és foglalási gomb szövege. Saját logó/nyitókép/galéria, választható csapat- és galériaszakasz, mobilmenü és alsó foglalási sáv.
- A szöveg/háttér színekhez legalább 4,5:1 mentési kontrasztellenőrzés, automatikus gombfeliratszín. Név, bemutatkozás, kapcsolat, nyitvatartás és feltételek továbbra is szabadon szerkeszthetők.
- Szolgáltatások: kategória, fix/induló/tájékoztató ár, valódi időtartam, elő- és utópuffer, alkalmas szakemberek, archiválás.
- Szakemberenként heti munkarend, szünet, napi kivétel, szabadság és blokkolás; külön szalonnyitvatartás.
- Vendégfiók nélküli online foglalás és közös szabályokkal ellenőrzött kézi felvitel. A „mindegy, kihez” választás után konkrét szakember látható minden választható időpontnál.
- Adatbázis-tranzakcióval sorosított véglegesítés, idempotens beküldés, mentéskor ismételt szabadidő-ellenőrzés.
- Mai nap, következő vendég, napi/heti naptár, áthelyezés, lemondás, elvégzett és meg nem jelent státusz, tényleges szolgáltatási összeg.
- Vendégkeresés, előzmények, kifejezett meglévővendég-választás, következő időpont, kontaktszerkesztés, engedélyhez kötött titkosított megjegyzés.
- A nevek/elérhetőségek egyezése nem egyesít kartonokat. A foglaláskori ár, név és időtartam változatlan marad.
- Tulajdonosi és saját naptárra korlátozott munkatársi hozzáférés; külön módosítási és megjegyzési engedélyek.
- Tulajdonosi JSON-export; törléshez jelszó és külön megerősítés szükséges. A személyes adatok névtelenítése megőrzi a látogatási összesítést.
- Lejáró, véletlen kezelési link; GET-kérés nem módosít adatot. Lemondási és áthelyezési határidő.
- Tartós értesítési sor visszaigazoláshoz, módosításhoz, lemondáshoz és emlékeztetőhöz, újrapróbálkozással és kezelői naplóval.
- Titkosított adatbázis- és hivatkozott médiamentés, üres célba visszaállítás.

A működési összesítésben az elvégzett szolgáltatások rögzített összege szerepel. Ez nem igazolt beérkezett pénz, és nem könyvelés.

## Új, elkülönített telepítés

PHP 8.4+, Composer és SQLite vagy MySQL/MariaDB szükséges. A PHP-bővítmények követelményeit a Composer ellenőrzi. Ugyanazt a kiadást telepítsd minden ügyfélnél, külön konfigurációval, APP_KEY-vel és adatbázissal.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File database/database.sqlite
php artisan migrate
```

Az új telepítés `.env` fájljában állíts be legalább 12 karakteres `SALON_ADMIN_PASSWORD` értéket, majd:

```powershell
php artisan salon:install --email=tulajdonos@example.hu
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8095
```

A `salon:install` nem ír felül meglévő telepítést. Demóhoz új, üres adatbázisban add meg a `--demo=salon`, `--demo=barber` vagy `--demo=nails` kapcsolót. Demót ne telepíts éles ügyféladatbázisba.

A belépés után a Beállítások induló lépései vezetnek végig az adatokon, arculaton, munkatársakon, szolgáltatásokon, tesztfoglaláson és előnézeten. Aktív szolgáltatás, alkalmas szakember és elegendő közös munkaidő nélkül nem engedélyezhető a közzététel. Nem demó telepítésnél a bemutató adatkezelési jelölő és a helyőrző telefonszám is megakadályozza a közzétételt; saját tartalom és valódi telefonszám szükséges. Ez tartalmi alapellenőrzés, nem az adatkezelési tájékoztató jogi jóváhagyása. Saját nyitókép nélkül a valódi szalon szöveges nyitóoldalt kap; a generált bemutató fotók nem jelennek meg.

A képfelhasználási jogok elfogadásának idejét mentjük. Korábban elfogadott jogok mellett a szöveges vagy működési beállítások mentéséhez nem kell új jelölés; új logó, nyitókép vagy galériakép feltöltésekor ismét meg kell erősíteni. A korábbi kiadásokban nem tárolt elfogadást az első ilyen mentésnél egyszer rögzíteni kell. Közzététel előtt a nyitóoldal és az adatkezelési oldal csak a tulajdonosi előnézetben érhető el.

Tulajdonosi vagy munkatársi jelszó helyreállítása operátori hozzáféréssel: állíts be egy 12–200 karakteres `SALON_ADMIN_PASSWORD` környezeti értéket, frissítsd az alkalmazás konfigurációs gyorsítótárát, majd futtasd: `php artisan salon:reset-password email@example.hu --no-interaction`. A régi `salon:password` név is működik. A jelszó nem parancssori argumentum és nem kerül kimenetbe. A művelet visszavonja az érintett felhasználó emlékező tokenjét és adatbázisos munkameneteit; más felhasználóét megőrzi. A telepítés alapértelmezett munkamenet-kezelője adatbázisos; más munkamenet-tárolót az üzemeltetőnek külön kell érvénytelenítenie. A helyreállítás után szüntesd meg az ideiglenes jelszóbeállítást, és frissítsd újra a konfigurációs gyorsítótárat.

A belépési oldal „Elfelejtetted a jelszavad?” hivatkozása tulajdonosnak és munkatársnak is elérhető. A webes kérés mindig ugyanazt az üzenetet adja; legfeljebb 5 kérés engedett óránként e-mail-címenként és IP-címenként. A levél egyszer használható, 60 percig érvényes linket tartalmaz, a token csak hash formában kerül az adatbázisba. Az új, megerősített jelszó 12–200 karakteres. A csere visszavonja a régi belépéseket, és értesítő levelet küld; a token megnyitása önmagában nem cserél jelszót. Az adminban a jelszóváltozást a munkamenet-ellenőrzés is figyeli. A webes visszaállításhoz működő, közvetlen levélküldő és helyes `APP_URL` szükséges; élesben hiányos konfigurációval nem küldünk resetlinket. Helyben `array` vagy helyi SMTP-gyűjtő is használható. A `salon:doctor` külön jelzi, ha valódi kézbesítés nincs beállítva; ilyenkor az operátori parancs marad a tartalék.

## Értesítések

```powershell
php artisan salon:notify
php artisan schedule:work
```

A demó `MAIL_MAILER=array` beállítása nem küld valódi e-mailt, és nem írja naplóba a kezelési linket. Az „helyi próba feldolgozva” állapot nem kézbesítési igazolás.

A foglalási és értékeléskérő levél HTML- és egyszerű szöveges részt is tartalmaz. A feladó megjelenített neve a szalon neve, a Reply-To a szalon e-mail-címe; a hitelesített küldőcím továbbra is a `MAIL_FROM_ADDRESS`. A `salon:notify` az adott futás sikeres és hibás küldési próbálkozásainak számát is kiírja.

Élesben `MAIL_MAILER=smtp` és a szolgáltató paraméterei szükségesek; a `log` levélküldőt és azt tartalmazó failovert ne használd, mert bizalmas kezelési link kerülhetne naplóba. A feladó domain SPF/DKIM/DMARC beállítása és a tényleges postaládás próba külön üzemeltetési feladat.

A kiszolgáló időzítője percenként futtassa a `php artisan schedule:run` parancsot. Nincs külön, folyamatos Laravel queue-worker követelmény. A jelszókérő minden érvényes e-mail-címre azonos módon egy titkosított feladatot ír a `password-resets` adatbázisos sorba; a fiókkeresés, tokenkészítés és levélküldés kizárólag a külön feldolgozóban történik. Ezt az időzítő percenként indítja a `queue:work database --queue=password-resets --stop-when-empty --max-time=50 --tries=1` paranccsal. A visszaállító levél ezért általában a következő percben indul; a 60 perces tokenlejárat a feldolgozáskor kezdődik. Ütemezés nélkül a kérés sorban marad. A jelszólevél küldési hibája általános, titkot nem tartalmazó figyelmeztetést ír; új link kérhető a kéréskorlát betartásával.

A foglalási levelek küldésének sikertelensége nem vonja vissza a foglalást. Öt próbálkozás után kezelői újraindítás kell. A visszalépés 2, 4, 8, 16 perc. Módosítás/lemondás érvényteleníti a régi revízió sorban álló értesítéseit. Fájl alapú SQLite esetén a kapcsolat WAL naplózást, IMMEDIATE tranzakciókat és 10 másodperces foglaltsági várakozást használ. A `salon:doctor` jelzi, ha a tényleges naplózási mód nem WAL. WAL mellett mentéshez a `salon:backup` parancsot használd; a `.sqlite` fájl önmagában történő másolása nem feltétlenül tartalmazza a legutóbbi adatokat.

A doctor külön jelzi az öt percnél régebbi, már esedékes jelszó-visszaállítási feladatokat és az elmúlt órában történt sikertelen visszaállító levélküldést. A hibajelzés csak időbélyeget tárol a cache-ben, e-mail-címet és tokent nem; ismeretlen cím feldolgozása nem törli a figyelmeztetést. A levélhiba továbbra sem indít automatikus újrapróbálást, új hivatkozás kérhető. Helyi fejlesztéskor az SMTP és a cron szándékosan kikapcsolva maradhat, ezért ezek doctor-figyelmeztetései várhatók.

Az emlékeztetési időn belüli új foglalás csak visszaigazolást kap. Az SMTP által átvett üzenet nem kézbesítési bizonyíték. Bizonytalan SMTP-válasz vagy folyamatleállás után ismételt kézbesítés előfordulhat; a stabil Message-ID segítheti a címzett oldali felismerést, nem garantálja a szolgáltatói idempotenciát. A rövid sorlefoglalás után az SMTP-hívás adatbáziszár nélkül fut. A küldés megkezdésekor még érvényes, már úton lévő levelet egy közben történő lemondás nem tudja visszahívni; a még sorban álló régi értesítéseket érvénytelenítjük. A megszakadt feldolgozás öt perc után ismét próbálható.

## Mentés és visszaállítás

```powershell
php artisan salon:backup
php artisan salon:restore "D:\mentesek\szalon.backup"
```

A mentés alaphelye `storage/app/private/backups`; az időzítő naponta 02:15 UTC-kor futtatja. Az export az alkalmazás adatait, arculati beállításait és az aktuális logó/nyitókép/galéria hivatkozott képeit tartalmazza, APP_KEY-vel titkosítva. A mellékelt generált képek a kiadási csomag részei. Az APP_KEY külön, biztonságos megőrzése nélkül nincs visszaállítás. A mentést másik tárolóra is másolni kell; ezt a helyi projekt nem állította be.

A régi, automatikusan elnevezett mentések takarítása külön operátori parancs. Előnézet: `php artisan salon:prune-backups --days=30 --dry-run --no-interaction`; törlés ugyanígy a `--dry-run` nélkül. Csak a mentési könyvtár időbélyeges `.backup` fájljait érinti, a legutóbbi mentést korától függetlenül megtartja, az egyedi nevű exportokhoz nem nyúl. A Laravel-időzítő vasárnap 03:15 UTC-kor futtatja 30 napos megőrzéssel; ehhez is szükséges a telepítésnél előírt percenkénti `schedule:run` cron.

Visszaállítás kizárólag azonos verziójú migrációkkal előkészített, üres adatbázisba lehetséges. Meglévő adatot vagy képfájlt nem ír felül. A helyreállítás alatt a webes forgalom és az időzítő álljon; újraindítás előtt ellenőrizd az értesítési sort, mert a mentés óta már elküldött üzenet újra esedékes lehet. A korábbi mentések személyes adatainak életciklusát is kezeld a saját megőrzési szabályzatodban.

## Ellenőrzés és kiadás

```powershell
php artisan test --compact
php artisan view:cache
php artisan route:list --except-vendor
composer validate --strict
```

A tesztek lefedik a külön folyamatokból érkező párhuzamos foglalást, dupla beküldést, puffert, szünetet, szabadságot, határidőket, időközben betelő időpontot, sikertelen és sikeres áthelyezést, történeti árat és archiválást, hozzáférési köröket, levélhibát/újrapróbálkozást, elavult emlékeztetőt, óraátállítást, explicit közzétételt, SVG-tiltást, törlést, valamint az adat- és médiavisszaállítást.

Böngészős bizonyítékok: `output/playwright/` (helyi, nem verziózott). Ellenőrzött nyilvános mobilfoglalás, tulajdonosi belépés, kézi következő időpont, áthelyezés, lemondás, napi/heti naptár és 320/390/1440 px-es elrendezések.

A prémium arculat képernyőképei és összehasonlításai: `output/design/`. Figma: https://www.figma.com/design/KpvtZCXgsAV78AENAzP2qZ (szerkeszthető arculati alapok és a beépített képkészlet). A három helyi demó adatbázisa szándékosan elkülönül.

Éles kiadáskor:

1. Azonos commit/kiadási csomag, ügyfelenként külön konfiguráció; `composer install --no-dev --prefer-dist --no-interaction`.
2. HTTPS, webszerver-gyökér kizárólag `public/`; `APP_ENV=production`, `APP_DEBUG=false`, valós `APP_URL`, `SESSION_SECURE_COOKIE=true`, külön süti azonos gépen futó példányokhoz.
3. Mentés, karbantartás, `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`, majd HTTP- és foglalási próba. Az APP_KEY-t frissítéskor őrizd meg.
4. Percenkénti időzítő, tényleges SMTP-próba, külső mentéstároló és helyreállítási próba.
5. Valós szolgáltatói tartalom, jogosított képek, ellenőrzött adatkezelési/foglalási feltételek, és valós pilot. A demó adatkezelési szövege nem éles dokumentum.

Helyben SQLite-on történt az ellenőrzés; a MySQL/MariaDB és a választott tárhely telepítési próbája élesítés előtt szükséges. Fizetés, készlet, piactér, több telephely és közös kezelőhelyiség kapacitáskezelése későbbi modul.

## Változásnapló

- 1.2.7 (2026-10-27, folyamatban): mobilos gyorsfoglalás, blokk és szabadság a Mai napon; összecsukható figyelmeztetések, foglalási gyorsműveletek, nagyobb mezők és időpontcélok, szabad időpontok automatikus frissítése, vendéglista-szűrés és a nyilvános foglalás lépései közötti navigáció. A teljes mobilos elfogadási ellenőrzés még hátravan.
- 1.2.6 (2026-10-26): nyilvános akcentusszövegek és Lemondva jelvény kontrasztjavítása, fekete/fehér automatikus gombfelirat teljes AA-tartománnyal; 429-es várakozás percben 60 másodperc fölött és belépési visszalépés; üres jelszómegerősítésnél egyetlen üzenet; doctor-jelzés a régi jelszókérési sorra és a közelmúltbeli levélhibára.
- 1.2.5 (2026-10-26): munkatársválasztók mentett neveinek megőrzése hibás új munkatársnál; SQLite WAL és IMMEDIATE, doctor-figyelmeztetéssel; teljes jelszókérési feldolgozás külön adatbázisos sorban, percenkénti ütemezéssel; sötét egyedi akcentus kontrasztjavítása a főoldalon; magyar validációs szövegek, 429 várakozási idő és visszalépés, vendég/kezelő lemondásának megkülönböztetése a levélben, lemondott foglalásnál feltételek elrejtése és fájl-újraválasztási segítség.
- 1.2.4 (2026-10-26): magyar webes jelszó-visszaállítás, lejáró és egyszer használható tokenek, kéréskorlát, munkamenet-visszavonás és értesítő levél; érintett munkatársi űrlap teljes visszatöltése; mentett képfelhasználási elfogadás; magyar telefonszám-helyőrzők normalizált ellenőrzése; magyar parancsleírások, szalonnévre és elérhetőségekre szabott hibaoldal, középre rendezett kép nélküli nyitóoldal, lemondási levél pontosítása, adatkezelési előnézet és heti mentéstakarítás.
- 1.2.3 (2026-10-26): javított nyitvatartás-visszatöltés hibás szalon- és munkatársmentésnél; bemutató adatok közzétételének tiltása valódi telepítésen; közelgő vendég kontrasztja és nagyobb adminfeliratok; dokumentált jelszó-helyreállítás; magyar hibaoldalak; saját fotó vagy kép nélküli valódi szalonoldal; személyesebb, szöveges változatot is tartalmazó levelek, vendégüzenetek, ingyenes árjelölés, foglalási horizont magyarázata, értesítési hibaszám és külön mentéstakarító parancs.
- 1.2.2 (2026-10-26): javított admin kontraszt az időpontválasztóban, vendéglistában, foglalásrészletekben és értesítésekben; olvashatóbb vendégmonogramok és lemondott státuszjelölők, 12 px-es szakembernevek és vendégkarton-feliratok.
- 1.2.1 (2026-10-25): javított magyar telefonszám- és mezővalidáció, konfigurációval elszigetelt telepítőtesztek, olvashatóbb adminszövegek, kikapcsolt SMS-modulhoz igazított diagnosztika, névvel és forrással kiegészített foglalásrészletek, billentyűzettel elérhető idővonal és múltbeli dátum magyarázata.

## Opcionális SMS (Twilio)

A Modulok oldalon külön kapcsolható a megosztás és QR, a meg nem jelenés elleni védelem, a telefonközpontú foglalás, az értékeléskérés, az ajándékutalvány, a statisztika és a bizonylatkezelés. Alapból kikapcsoltak. Az elérhetőség, lemondási szabály és értékeléskérés itt állítható be; az utalvány kiadása és beváltása tulajdonosi művelet. Az online/admin foglalási csatorna a meglévő `bookings.source`, a Google/Facebook/Instagram/QR/SMS eredet a külön `referral_source` mezőben marad meg.

A QA2 háromprofilos, 27 foglalással feltöltött helyi próbája és a 320/390/768/1440 px-es képek az `output/playwright/qa2-20261025/` alatt találhatók. A szintetikus adatbázisok külön állnak a meglévő demóktól. A helyi tesztek nem igazolják a valódi szolgáltatói kézbesítést vagy az éles adatbázis kompatibilitását.

A tulajdonosi Modulok oldalon kapcsolható be. Az üzemeltető állítsa be a `SMS_PROVIDER=twilio`, `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN` és `TWILIO_SENDER` konfigurációt; a titkos értékeket ne küldje dokumentációba vagy naplóba. A feladó és a célország legyen engedélyezve a Twilio-fiókban. A `salon:sms-notify` parancsot az időzítő percenként futtatja. A foglalásban külön hozzájárulás kell az SMS-emlékeztetőhöz. Az „átvette” állapot nem kézbesítés: valódi telefonszámos próba külön szükséges. Bizonytalan kimenetelnél előbb a szolgáltatónál egyeztess, ne küldj vakon újra.

## Működési ellenőrzés

Az opcionális Számlázz.hu modul a tulajdonosi Bizonylatok oldalon állítható be; az Agent-kulcs titkosítva tárolódik, a felület nem jeleníti meg. A valódi küldés külön kapcsolóval engedélyezhető, alapból tiltott. A bizonylattípust és az áfakulcsot könyvelő hagyja jóvá. Kapcsold be a szolgáltatónál a rendelésszám ismétlődésének tiltását számlához és nyugtához is. Foglalásonként egy helyi bizonylatkérés készül; bizonytalan válaszra kizárólag lekérdezéses egyeztetés indítható. Az elutasított kérés ugyanazzal az azonosítóval újrapróbálható. A PDF és a vevőadatokat tartalmazó kérési pillanatkép titkosított. A bizonylatok megőrzését és szolgáltatói sztornóját külön, a szalon számviteli rendje szerint kell kezelni; a vendégnévtelenítés nem törli a számviteli bizonylatot.

Az XML-sémák a [Számlázz.hu hivatalos dokumentációjából](https://docs.szamlazz.hu/agent/) származnak (2026-10-25), helyi teszteléshez a `resources/schemas/` alatt vannak. A számlát és a nyugtát kizárólag `Http::fake` tesztek ellenőrzik; valódi bizonylat nem készült. A NAV nyugtaadat-szolgáltatás automatikus teljesítését ez a modul nem igazolja: a [szolgáltató nyugta API-leírása](https://docs.szamlazz.hu/agent/generating_receipt/request) szerint az automatizálás fejlesztés alatt áll. Külön szolgáltatói és könyvelői ellenőrzés szükséges.

A kiadás verziójának egyetlen forrása a `config/salon.php`. A `php artisan salon:doctor` módosítás és küldés nélkül ellenőrzi a sémát, a feldolgozási szívverést, az esedékes sorokat és az értesítési konfigurációt. Hibánál nem nulla kilépési kódot ad. A hiányzó vagy öt percnél régebbi szívverés figyelmeztetés; a parancs helyi levélpróbánál is jelez. Ez nem külső szolgáltatói készültségi igazolás. Az Értesítések oldalon az utolsó feldolgozás budapesti időben látható. Frissítés előtt készíts mentést, majd minden külön telepítésen futtasd a migrációkat.
