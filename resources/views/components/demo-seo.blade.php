@php
    $demoIndexable = app(\App\Services\DemoJourney::class)->indexable();
    $demoSeoTitle = $salon->theme === 'barber' ? 'SzalonPRO barber bemutató – hajvágás és szakállfoglalás' : ($salon->theme === 'nails' ? 'SzalonPRO körmös bemutató – manikűr és műköröm foglalás' : 'SzalonPRO – online időpontfoglaló és szalonkezelő');
    $demoSeoDescription = $salon->theme === 'barber' ? 'Barber bemutató hajvágással, szakálligazítással, szakemberválasztással és olvasó adminfelülettel. Kizárólag mintaadatok.' : ($salon->theme === 'nails' ? 'Körmös bemutató manikűrrel, géllakkal, műkörömépítéssel és olvasó adminfelülettel. Kizárólag mintaadatok.' : 'Online időpontfoglalás és szalonkezelés fodrászatoknak, barbereknek és körmösöknek. 4 900 Ft/hó, bevezetési díj nélkül. Próbáld ki a demót.');
@endphp
<meta name="robots" content="{{ $demoIndexable ? 'index, follow' : 'noindex, follow' }}">
<link rel="canonical" href="https://szalon.pro/">
<meta property="og:type" content="website"><meta property="og:locale" content="hu_HU">
<meta property="og:title" content="{{ $demoSeoTitle }}"><meta property="og:description" content="{{ $demoSeoDescription }}">
<meta property="og:url" content="https://szalon.pro/"><meta property="og:image" content="https://szalon.pro/assets/branding/hair-hero-mobile.webp">
@if($demoIndexable)
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => 'SzalonPRO', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'url' => 'https://szalon.pro/', 'offers' => ['@type' => 'Offer', 'url' => \App\Services\DemoJourney::ProductUrl, 'price' => 4900, 'priceCurrency' => 'HUF', 'description' => 'ÁFA-mentes ár (alanyi adómentes). Havonta fizetendő, bevezetési díj nélkül.', 'priceSpecification' => ['@type' => 'UnitPriceSpecification', 'price' => 4900, 'priceCurrency' => 'HUF', 'billingDuration' => 'P1M', 'unitText' => 'hó']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endif
