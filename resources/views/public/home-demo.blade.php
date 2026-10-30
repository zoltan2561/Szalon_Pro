<div data-demo-hub>
    <section class="demo-hero wrap" aria-labelledby="demo-title">
        <div class="demo-hero-copy">
            <p class="eyebrow">SZALONPRO · A VENDÉGEIDNEK ÉS NEKED</p>
            <h1 id="demo-title">Több figyelem a vendégnek.<br><em>Tisztább kép a napodról.</em></h1>
            <p class="demo-lead">A vendéged időpontot foglal. Te átlátod, ki érkezik, mikor van szabad hely, és mi történt az előző alkalommal. Fodrászatnak, barbernek és manikűrösnek, a saját arculatoddal.</p>
            <div class="demo-hero-actions">@if($demoAccessAvailable)@include('components.demo-entry', ['showHint' => false])@else<a class="button" href="#napi-munka">Tulajdonosi bemutató <x-icon name="calendar-blank" /></a>@endif<a class="button secondary" href="{{ route('booking.form') }}">Vendégként kipróbálom <x-icon name="arrow-right" /></a></div>
            <p class="demo-trial-note">Működő bemutató mintaadatokkal. Az admin jelszó nélkül, olvasóként járható be; a vendégfoglaláshoz nem kell fiók.</p>
        </div>
        <figure class="demo-hero-photo"><img src="{{ asset('assets/branding/hair-hero-mobile.webp') }}" alt="Világos szalonbelső tükrökkel és fodrászszékekkel" width="720" height="960" fetchpriority="high"><figcaption>Fodrászat · Barber · Manikűr<br><span>AI-generált bemutató hangulatkép</span></figcaption></figure>
    </section>

    <section class="demo-industries wrap" id="szakagak" aria-labelledby="demo-industries-title">
        <div class="demo-section-heading"><div><p class="eyebrow">A TE SZAKMÁD. A TE HANGULATOD.</p><h2 id="demo-industries-title">Nézd meg a saját szakágad.</h2></div><p>Három külön bemutató, saját szolgáltatásokkal és naptárral. Válassz egyet, és járd végig a vendéged útját.</p></div>
        <div class="demo-industry-grid">
            @foreach(['salon' => ['name' => 'Fodrászat', 'image' => 'hair', 'copy' => 'Vágás, szín és a következő jó hajnap.', 'detail' => 'Szolgáltatások, szakemberválasztás és korábbi színrecept.'], 'barber' => ['name' => 'Barber', 'image' => 'barber', 'copy' => 'Precíz vágás. Rendezett nap.', 'detail' => 'Haj és szakáll, betérő vendégek és visszatérő fazonok.'], 'nails' => ['name' => 'Manikűr és műköröm', 'image' => 'nails', 'copy' => 'Szín, forma, személyes figyelem.', 'detail' => 'Kezelések, következő alkalmak és szakmai munkalap.']] as $style => $profile)
                @php
                    $profileUrl = $demoUrls[$style] ?? ($style === $brand['theme'] ? $demoGuestHomeUrl : null);
                    if ($profileUrl && rtrim($profileUrl, '/') === rtrim(route('home'), '/')) {
                        $profileUrl = $demoGuestHomeUrl;
                    }
                @endphp
                <article class="demo-industry demo-industry-{{ $style }}">
                    <div class="demo-industry-photo"><img src="{{ asset('assets/branding/'.$profile['image'].'-studio.webp') }}" alt="{{ $profile['name'] }} – AI-generált bemutató szalonbelső" width="960" height="720" loading="lazy"></div>
                    <div class="demo-industry-copy"><p class="eyebrow">{{ $profile['name'] }}</p><h3>{{ $profile['copy'] }}</h3><p>{{ $profile['detail'] }}</p>@if($profileUrl)<a class="demo-industry-link" href="{{ $profileUrl }}">{{ $profile['name'] }} kipróbálása <x-icon name="arrow-right" /></a>@else<p class="muted">Ez a szakági bemutató ezen a címen még nincs összekapcsolva.</p>@endif</div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="demo-workday" id="napi-munka" aria-labelledby="demo-workday-title"><div class="wrap">
        <div class="demo-section-heading"><div><p class="eyebrow">A SZÉK MÖGÖTT IS SZÁMÍT AZ IDŐD</p><h2 id="demo-workday-title">Tudd, mi következik.<br>Legyen kéznél, ami kell.</h2></div><p>A napi munkához tartozó műveletek egy helyen: érkezés, munkalap, lezárás és következő időpont.</p></div>
        <ol class="demo-workflow">
            <li><span class="demo-workflow-number">1</span><div><h3>A vendéged foglal</h3><p>Szolgáltatást, szakembert és szabad időpontot választ. Az árat és az időtartamot előre látja.</p><a href="{{ route('booking.form') }}">Kipróbálom a foglalást <x-icon name="arrow-right" /></a></div></li>
            <li><span class="demo-workflow-number">2</span><div><h3>Te felkészülten várod</h3><p>Látod a mai vendégeket és a következő érkezést. A munkalap, a vendégelőzmények és a következő alkalom ugyaninnen elérhető.</p>@if($demoAccessAvailable)@include('components.demo-entry', ['label' => 'Megnézem a munkafelületet', 'secondary' => true, 'showHint' => false])@else<a href="{{ route('admin.today') }}">Tulajdonosi felület megnyitása <x-icon name="arrow-right" /></a>@endif<small>A nyilvános demóban a vendégadatok védettek, mentés nélkül nézelődhetsz.</small></div></li>
            <li><span class="demo-workflow-number">3</span><div><h3>A következő alkalom is rendezett</h3><p>Rögzíted a munkát, visszanézed az előzményeket, és a meglévő vendégnek új időpontot keresel.</p><a href="{{ route('admin.guests') }}">Vendégkezelés megnyitása <x-icon name="arrow-right" /></a></div></li>
        </ol>
        <div class="demo-roles"><article><x-icon name="scissors" /><div><h3>Munkatársként</h3><p>A saját napod, vendégeid és a munkádhoz engedélyezett műveletek kerülnek előtérbe.</p></div></article><article><x-icon name="users" /><div><h3>Tulajdonosként</h3><p>A csapat naptárát, a szolgáltatásokat és az üzlet működését is áttekintheted.</p></div></article></div>
    </div></section>

    <section class="demo-brand-section wrap" id="sajat-arculat" aria-labelledby="demo-brand-title"><p class="eyebrow">A SAJÁT SZALONODRA HANGOLVA</p><div class="demo-brand-grid"><h2 id="demo-brand-title">A vendég a te<br>szalonodat ismerje fel.</h2><div><p>Saját név, logó, színek és képek. A szolgáltatásaid, áraiddal és időtartamaiddal. A csapatod, a saját munkarendjével.</p><p>A szakági bemutatók kiindulópontok. A megrendelt felületet a vállalkozásod arculatához és működéséhez igazítjuk.</p><a class="demo-text-link" href="{{ $demoGuestHomeUrl }}">Megnézem a vendégoldal példáját <x-icon name="arrow-right" /></a></div></div></section>

    <section class="demo-contact" id="kapcsolat" aria-labelledby="demo-contact-title"><div class="wrap"><p class="eyebrow">NÉZD MEG, HOGYAN ILLENE HOZZÁD</p><h2 id="demo-contact-title">A következő bemutató<br>szóljon a te szalonodról.</h2><p>Írd meg, milyen szakágban dolgozol, egyedül vagy csapattal, és mire szeretnél több időt hagyni a napodban.</p><div class="demo-contact-actions">@if($demoSalesContactUrl)<a class="button" href="{{ $demoSalesContactUrl }}">Saját szalont szeretnék <x-icon name="arrow-right" /></a>@endif<a class="demo-contact-secondary" href="#szakagak">Előbb kipróbálom a demókat</a></div><p class="demo-disclosure">A bemutatóban szereplő szalonok, vendégek és foglalások mintaadatok. Kérjük, valódi személyes adatot ne adj meg.</p></div></section>
</div>
