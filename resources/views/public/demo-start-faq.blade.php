<section class="demo-start wrap" aria-labelledby="demo-start-title">
    <p class="eyebrow">SAJÁT SZALONPRO</p><h2 id="demo-start-title">Így indulsz</h2>
    <ol role="list" class="demo-workflow" data-demo-start>
        <li><span class="demo-workflow-number" aria-hidden="true">1</span><div><h3>Ajánlatot kérsz</h3><p>Írd meg, milyen szalonnak keresel rendszert.</p><a href="{{ app(\App\Services\DemoJourney::class)->contact('start') }}">Ajánlatot kérek <x-icon name="arrow-right" /></a></div><span class="demo-workflow-arrow" aria-hidden="true"><x-icon name="arrow-right" /></span></li>
        <li><span class="demo-workflow-number" aria-hidden="true">2</span><div><h3>Saját aldomaint kapsz</h3><p>A saját felületed bevezetési díj nélkül indul, például a szalonod.szalon.pro címen.</p></div><span class="demo-workflow-arrow" aria-hidden="true"><x-icon name="arrow-right" /></span></li>
        <li><span class="demo-workflow-number" aria-hidden="true">3</span><div><h3>Feltöltöd a saját adataidat</h3><p>Te adod meg a szolgáltatásokat, munkatársakat és a nyitvatartást.</p></div></li>
    </ol>
    <p><a href="https://szoftlab.hu/termekek/szalonpro">Részletek és árak</a></p>
</section>
<section class="demo-faq wrap" aria-labelledby="demo-faq-title">
    <h2 id="demo-faq-title">Gyakori kérdések</h2>
    @foreach(app(\App\Services\DemoJourney::class)->faq() as $question => $answer)
        <details><summary>{{ $question }}</summary><p>{{ $answer }}</p></details>
    @endforeach
    <p><a href="{{ app(\App\Services\DemoJourney::class)->contact('faq', 'question') }}">Kérdésem van</a> · <a href="https://szoftlab.hu/aszf">Részletes szerződési feltételek</a></p>
</section>
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect(app(\App\Services\DemoJourney::class)->faq())->map(fn ($answer, $question) => ['@type' => 'Question', 'name' => $question, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer]])->values()->all()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
