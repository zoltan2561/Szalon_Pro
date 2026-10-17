<section class="journey-section wrap" aria-labelledby="journey-title">
    <div class="section-heading"><div><p class="eyebrow">EGYSZERŰ IDŐPONTFOGLALÁS</p><h2 id="journey-title">{{ $salon->demo ? $brand['demo_copy']['journey_heading'] : 'Néhány lépés, és várunk.' }}</h2></div><p>A foglaláshoz nem kell vendégfiókot létrehoznod.</p></div>
    <div class="journey-grid">
        <article><span class="journey-number">01</span><x-icon name="scissors" /><h3>{{ $salon->demo ? $brand['demo_copy']['journey_service'] : 'Válassz szolgáltatást' }}</h3><p>Az árat és az időtartamot már a választás előtt látod.</p></article>
        <article><span class="journey-number">02</span><x-icon name="calendar-blank" /><h3>Találd meg az időpontod</h3><p>Választhatsz szakembert, majd megnézheted a szabad helyeket.</p></article>
        <article><span class="journey-number">03</span><x-icon name="check" /><h3>Véglegesítsd a foglalást</h3><p>A visszaigazolás után a foglalás módosítható vagy lemondható a feltételek szerint.</p></article>
    </div>
</section>
