# SzalonPRO – közös kódbázis és ügyfélpéldányok

Forrás: a [2026. november 2-i munkautasítás](https://szoftlabworkspace.slack.com/archives/C0C911JUM6V/p1793607539341439). Ez a dokumentum az új működést és a telepítéshez szükséges infrastruktúrát írja le; nem igazol éles DNS-, SSL- vagy adatbázis-módosítást.

## Adatok és meglévő demók

Egy Laravel-alkalmazás, egy telepített verzió és egy közös adatbázis szolgálja ki a példányokat. Az ügyféladatok `tenant_id` mezőt kapnak. A modellek globális szűrője, a kapcsolt rekordok tulajdonosának ellenőrzése és a tenant szerint szűrt validáció együtt védi az adatokat. A Stancl Tenancy egyadatbázisos módban fut; nem hoz létre külön ügyfél-adatbázisokat.

A migráció a meglévő rekordokat az első, `demo` azonosítójú példányhoz rendeli. Nem másolja vagy cseréli le a jelenlegi demó tartalmát. A meglévő `/admin` és `/belepes` felület megmarad. A korábban külön telepítésen vagy adatbázison működő barber/körmös demókat ez a migráció nem egyesíti: azokon is ugyanaz a kód és a saját adatbázisukhoz tartozó első `demo` példány használható, változatlan adattartalommal. Meglévő demóadatbázisokat ne tölts egymásra.

Ismeretlen domain nem kap automatikusan demóadatokat. A felfüggesztett példány HTTP 503 választ ad; az adatai megmaradnak. A demópéldány felfüggesztése tiltott. A tenantot és a munkamenetet a domain határozza meg, a munkamenetsüti hosthoz kötött. A főadmin joga nem jelent automatikus ügyféladat-hozzáférést más domainen.

## Főadmin és átadás

A főadmin a központi domain `/foadmin` címén érhető el. A hozzáférést egy meglévő demó-tulajdonosi fióknak lehet megadni a `salon:super-admin {email}` paranccsal. A parancs nem hoz létre alapértelmezett jelszót. Az éles jogosultságadás külön üzemeltetési művelet.

Az **Új ügyfélpéldány** űrlap az aldomain nevét, a szalon nevét és a tulajdonos e-mail-címét kéri. A tenant, az alapbeállítások és a tulajdonosi fiók egy tranzakcióban készülnek el. Nem másol vendéget, foglalást, szolgáltatást vagy munkatársat a demóból. Az aldomain létrehozás után nem módosítható; a szalon neve és az elérhetőségi állapot szerkeszthető.

A tulajdonosi fiók véletlen, nem közölt jelszót kap. A tulajdonos az új példány `/elfelejtett-jelszo` oldalán kérhet első jelszóbeállítást. Ehhez működő, hitelesített levélküldés és a jelszó-visszaállító sor feldolgozása szükséges. A példány létrehozása önmagában nem küld e-mailt.

A **Belépés a szalonba** művelet két percig érvényes, egyszer használható, az adott tenant és tulajdonos párosához kötött belépési jegyet ad. Csak a jegy hash-e kerül adatbázisba; a kezdeményező főadmin, a célszemély és a felhasználás időpontja visszaolvasható. A jegy másik domainen, lejárat után vagy a főadmin jogának visszavonása után nem használható. A `/szalon-admin` munkatérből a **Vissza a főadminba** gomb lezárja az ügyféloldali munkamenetet; a központi host külön munkamenete megmarad. A jegyekhez tartozó URL-eket ne másold naplóba vagy hibajegybe.

A tenant Filament-munkatere `/szalon-admin`. Innen elérhető a napi naptár, a foglaláslista, a vendéglista, a szolgáltatás- és árkezelés, a munkatársak, valamint az arculat és tartalom szerkesztése. Az új beállítások a saját szalonrekord JSON-adataiban maradnak, a közös kód és verzió változtatása nélkül.

## Vanessa helyi tesztpéldánya

A `salon:prepare-test-tenant --email=vanessa@example.test` parancs kizárólag helyi/tesztkörnyezetben készíti elő Vanessa példányát. Csak külön, eldobható helyi adatbázison futtasd; a már használatban lévő demóadatbázis és az éles adatbázis külön cél. A parancs ismételt futtatása nem hoz létre második Vanessa-példányt. Judit példánya nem része ennek az előkészítésnek: azt később Zoltán hozza létre a főadminból.

Ha a helyi gyökér `http://localhost:8095`, a beállított alapdomain `localhost`, akkor az új cím `http://vanessa.localhost:8095`. A böngészőnek/operációs rendszernek a nevet loopbackre kell feloldania; szükség esetén helyi hosts-bejegyzés kell. Ez nem nyilvános domainlétrehozás.

## Domainbeállítások

Példa a központi telepítéshez; a tényleges hostneveket az üzemeltető adja meg:

```dotenv
APP_URL=https://szalon.pro
TENANCY_BASE_DOMAIN=szalon.pro
TENANCY_CENTRAL_DOMAINS=szalon.pro
TENANCY_DEMO_DOMAINS=demo.szalon.pro
SESSION_SECURE_COOKIE=true
```

A `TENANCY_CENTRAL_DOMAINS` és `TENANCY_DEMO_DOMAINS` vesszővel elválasztott, protokoll és útvonal nélküli hostneveket tartalmaz. A már konfigurált szakági demócímek hostjai is demóhostként kezelhetők. A központi domain listája ne tartalmazzon ügyfél-aldomaint. A `TENANCY_BASE_DOMAIN` alatt a főadminban megadott névből keletkezik a teljes ügyfélhost.

Az új példány azonnali elérése akkor teljesül, ha az alábbi DNS-, webszerver- és SSL-réteg már előre működik. Az alkalmazás nem vásárol domaint, nem módosít DNS-t és nem kér tanúsítványt egy űrlapmentés részeként.

## Wildcard DNS és webszerver

A gyökérdomain és `*.szalon.pro` A/AAAA rekordja ugyanarra a kiszolgálóra mutasson. Csak a valóban működő IPv4/IPv6 cím kerüljön be; egy hibás AAAA rekord az IPv6-os látogatóknál hibát okozhat. Az e-mailhez és domainhitelesítéshez használt MX/TXT/DKIM/DMARC rekordokat meg kell tartani. Egy konkrét aldomain meglévő DNS-rekordja elsőbbséget élvezhet a wildcarddal szemben.

A webszerver fogadja a gyökérdomaint és a wildcardot ugyanazzal a Laravel `public` dokumentumgyökérrel. A projekt gyökere, `.env`, naplók és mentések nem lehetnek nyilvános dokumentumgyökérben. Hostinger megosztott tárhelynél külön ellenőrizni kell a wildcard aldomain és a hozzá tartozó tanúsítvány támogatását; a DNS-bejegyzés önmagában nem konfigurálja a virtuális hosztot.

### Caddy, DNS challenge használatával

Az alábbi példa saját szerveren használható; a PHP-FPM socket és a projektútvonal a valós környezethez igazítandó:

```caddyfile
szalon.pro, *.szalon.pro {
    root * /srv/szalon-pro/public
    php_fastcgi unix//run/php/php8.4-fpm.sock
    file_server
    tls {
        dns cloudflare {env.CLOUDFLARE_API_TOKEN}
    }
}
```

A wildcard tanúsítványhoz DNS challenge és a megfelelő DNS-szolgáltató Caddy-modulja kell. A DNS API-token kizárólag a szükséges zónára kapjon jogosultságot, védett környezeti tárolóból; ne kerüljön a Caddyfile-ba vagy Gitbe. A `*.szalon.pro` tanúsítvány a gyökérdomaint önmagában nem fedi le, ezért az is szerepel a konfigurációban. [Caddy: TLS](https://caddyserver.com/docs/caddyfile/directives/tls), [Caddy: gyakori konfigurációk](https://caddyserver.com/docs/caddyfile/patterns).

### Cloudflare-proxy

A wildcard DNS-rekord és a proxyn keresztüli kiszolgálás külön beállítás. Ellenőrizni kell, hogy az edge-tanúsítvány valóban lefedi a gyökérdomaint és az ügyfélhostokat. A kiszolgáló felé **Full (strict)** mód és érvényes, megfelelő hostnevekre kiállított origin-tanúsítvány szükséges. A Flexible mód nem alkalmas a végpontok közötti HTTPS kiváltására. A proxy megbízható címeit és a HTTPS fejlécek értelmezését a tényleges infrastruktúrához kell konfigurálni; tetszőleges kliens által küldött proxyfejlécekben nem szabad megbízni. [Cloudflare: wildcard DNS](https://developers.cloudflare.com/dns/manage-dns-records/reference/wildcard-dns-records/), [Cloudflare: Full (strict)](https://developers.cloudflare.com/ssl/origin-configuration/ssl-modes/full-strict/).

## Kiadás és visszaállítás

Az új ügyfélpéldányok Google Naptár OAuth-visszatérési címe a központi `APP_URL` alatti `/google-naptar/kozponti-visszateres`. Ezt előre fel kell venni a közös Google OAuth-kliens engedélyezett visszatérési címei közé. A központi végpont aláírt, rövid ideig érvényes állapot alapján irányít az aktív példány saját visszatérési címére; tetszőleges kliensoldali URL nem fogadható el. A meglévő demó külön Google-beállítása megmarad. A helyi, HTTP-klienssel helyettesített teszt nem valódi Google-kapcsolódási bizonyíték.

A korábbi `salon:backup` és `salon:restore` parancs kizárólag egyetlen bemutatópéldánnyal használható. Több példánynál szándékosan hibával leállnak, hogy ne készülhessen más ügyfelek adatait is tartalmazó régi szalonexport. Közös telepítésnél teljes adatbázis- és médiaállomány-mentés szükséges az üzemeltetői mentési rendszerből; ennek automatizálása és visszaállítási próbája külön kiadási feltétel.

Élesítés előtt az adatbázisról és a hivatkozott médiáról ellenőrzött, külső másolatot kell készíteni. A migrációt először a mentés külön tesztmásolatán kell kipróbálni. A jelenlegi demóoldalak és adminfolyamatok kimenetét a migráció előtt és után ugyanazzal a profillal kell ellenőrizni.

A kiadás telepítse a lock fájl szerinti csomagokat és a Filament asseteket (`php artisan filament:assets --no-interaction`), futtassa a migrációkat, majd frissítse a konfiguráció-, route- és view-cache-t. Az időzítők és a háttérmunkások az új verzióval induljanak újra. Ugyanazt a release-t kell minden megmaradt külön demótelepítésre kiadni; nem készül ügyfelenkénti forráskódág.

Az ellenőrzés külön pontjai: meglévő demók, új saját domain, tenantok közötti adatelkülönítés, jelszóbeállítás és levélcímzett, saját média, felfüggesztés/újraaktiválás, egyszer használható főadminbelépés, HTTP→HTTPS, gyökér- és wildcard-tanúsítvány, és az ütemezett tenantonkénti feldolgozás. A helyi PHPUnit-siker nem igazolja az éles DNS-t, SSL-t, SMTP-t vagy a hosting migrációját.

Több ügyfélpéldány létrejötte után a séma visszagörgetése nem biztonságos adatösszevonási módszer. Ilyenkor az egyeztetett teljes adatbázis- és médiamentésből, az ahhoz tartozó alkalmazásverzióval kell visszaállni; az időközben érkezett új ügyféladatok sorsát előre rendezni kell.
