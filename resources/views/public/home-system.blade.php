@if($salon->demo)
<section class="system-section" id="rendszer" aria-labelledby="system-title"><div class="wrap">
    <div class="system-heading"><div><p class="eyebrow">A VENDÉGOLDAL MÖGÖTT</p><h2 id="system-title">Egy szalonodhoz igazítható rendszer.</h2></div><p>Ez a működő minta megmutatja a vendégek útját. A tulajdonosi felületen ugyanennek a szalonnak a szolgáltatásai, munkarendje, foglalásai és vendégei kezelhetők.</p></div>
    <div class="system-grid">
        <article><x-icon name="sparkle" /><h3>Saját arculat</h3><p>Három kiinduló stílus, szerkeszthető színek és feliratok, saját logó és képek.</p></article>
        <article><x-icon name="users" /><h3>Szolgáltatás és csapat</h3><p>Árak, időtartamok, alkalmas szakemberek és egyéni munkarendek.</p></article>
        <article><x-icon name="calendar-blank" /><h3>Napi áttekintés</h3><p>Naptár, kézi foglalás, áthelyezés, lemondás és vendégelőzmények.</p></article>
    </div>
    <div class="system-fit">
        <p><strong>Kinek való?</strong> Önálló szépségipari szakembernek vagy egy szalon csapatának, akik közös naptárban szeretnék kezelni az időpontokat.</p>
        <p><strong>Hogyan indul?</strong> A saját arculat, szolgáltatások és munkaidők beállítása után ellenőrizhető a foglalási folyamat, majd közzétehető a vendégoldal.</p>
    </div>
    <div class="system-action"><div class="system-links"><a class="button" href="{{ route('booking.form') }}">Próbáld ki a foglalást <x-icon name="arrow-right" /></a>@if($demoSalesContactUrl)<a class="button secondary" href="{{ $demoSalesContactUrl }}">Saját szalont szeretnék <x-icon name="arrow-right" /></a>@endif</div><p>A bemutató mintaadatokat használ. Kérjük, ne adj meg valódi személyes adatot.</p></div>
</div></section>
@endif
