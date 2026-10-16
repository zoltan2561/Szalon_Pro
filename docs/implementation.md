# SzalonPRO – megvalósítási döntések

Forrás: a [p-szalon_pro csatorna](https://szoftlabworkspace.slack.com/archives/C0C911JUM6V) teljes, 2026-10-16-án elérhető története: 3 főüzenet, a projektterv 6 szálválasza. A teljes szöveg: `slack-specification.txt`. Az aktuális felhasználói kérés a korábban javaslatként rögzített MVP megvalósítását engedélyezi.

- Laravel 13, PHP 8.4+, Blade, saját CSS és kis mennyiségű JavaScript. A naptár szerveroldalon működik JavaScript nélkül is.
- Ügyfelenként külön telepítés és adatbázis. Nincs központi SaaS vagy közös ügyféladatbázis. A két demó is külön adatbázist használ.
- SQLite a helyi demóban, MySQL/MariaDB konfigurálható telepítéshez. A foglalások előtt ugyanannak a szalonrekordnak a tranzakciós írása sorosítja az ellenőrzést és mentést. A szolgáltató kis csapatára ez tudatosan egyszerű megoldás.
- UTC tárolás, Europe/Budapest megjelenítés; az időpontválasztó konkrét UTC-eltolású értéket küld. Egy foglalás egy szolgáltatás és egy szakember.
- A vendégkartonokat a rendszer nem egyesíti automatikusan. A meglévő vendéget az admin kifejezetten választja ki.
- Alapállapot: visszaigazolt. Áthelyezés esemény és új revízió; nem külön státusz. Az előző név, időtartam és ár pillanatképként megmarad.
- Tartós adatbázisos e-mail kimeneti sor: foglalási revízióhoz kötött értesítések, késleltetett emlékeztetők, próbálkozásszám, exponenciális újrapróbálkozás, kezelői hibajelzés. A helyi alapértelmezett `array` levélküldő nem küld valódi e-mailt és nem naplózza a titkos linkeket.
- Foglaláskezelő token: véletlen 256 bites érték; kereséshez hash, levélküldéshez titkosított példány, lejárat a látogatás után 30 nappal. A GET sosem módosít foglalást.
- Az elvégzett szolgáltatások összege működési adat, nem igazolt pénzbevétel.
- Nincsenek kitalált értékelések vagy portrék. A demó szintetikus szolgáltatásait és munkatársait a felület jelöli. A képfeltöltés csak raszteres képeket fogad, SVG-t nem.

Kiadáson kívül: fizetés, SMS, készlet, számlázás, piactér, orvosi dokumentáció, több telephely, közösen használt kezelőhelyiség kapacitáskezelése. Az árazás, pilot kiválasztása és kereskedelmi határidő üzleti döntés marad.

Élesítés előtt szükséges a valós arculat és tartalom, az adatkezelési szöveg szolgáltatói ellenőrzése, HTTPS, tényleges levélküldő és domainhitelesítés, időzítő, külső mentéstároló és valós pilot. A helyi teszt nem ezek elvégzésének bizonyítéka.

## Ellenőrzött eredmény – 2026. október 16.

- Laravel 13.37.0 / PHP 8.4.21; `composer validate --strict`, Blade-cache és route-cache sikeres.
- Teljes tesztcsomag: 29 sikeres teszt, 143 állítás. Külön PHP-folyamatok közös rajtjelről ugyanarra az időpontra indított foglalása: egy mentés, egy ütközés, egy vendégkarton.
- Ellenőrzött UTC-tárolás, tavaszi és őszi óraátállítás, eltérő klienseltolás, teljes szolgáltatáshossz és pufferek, munkaidő, szünet, szabadság és időszakblokkolás.
- Levélhiba után a foglalás megmarad, a sor újrapróbálható. A külső küldés nem tart foglalási tranzakciót nyitva; az elhagyott feldolgozás helyreállítható. A sorban álló régi emlékeztetők módosítás/lemondás után elavultak.
- Mentés/visszaállítás: titkosított alkalmazásadatok és hivatkozott média; nem üres cél felülírásának tiltása; token és vendégadat visszaolvasása sikeres.
- Valódi Chrome/Playwright: 390 px-es nyilvános foglalás véglegesítése; tulajdonosi belépés; meglévő vendég kézi következő időpontja; 10:30-ról 13:00-ra áthelyezés; lemondás; napi/heti naptár visszaolvasása. A lezárás és meg nem jelenés HTTP-folyamatát az automatizált Laravel-tesztek fedik le.
- 320/390/1440 px-es nézetek; 320 px-en a beállítási oldal és a barber nyilvános oldal dokumentumszélessége nem lépi túl a nézet szélességét. Képek: `output/playwright/`.
- Külön `database.sqlite` / `barber.sqlite` és külön munkamenetsüti; a szalon böngészős próba-vendége nem jelenik meg a barber vendéglistájában.
- Helyi címek: szalon `http://127.0.0.1:8095`, barber `http://127.0.0.1:8096`. Mindkettő szintetikus demó; valódi SMTP, éles tárhely, MySQL/MariaDB futtatás és valós ügyfélpilot nem történt.
