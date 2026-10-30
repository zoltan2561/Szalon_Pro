<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="{{ $isDemoHub ? 'Online időpontfoglalás és napi szalonkezelés fodrászatoknak, barbereknek és manikűrösöknek. Próbáld ki vendégként és nézd meg a munkafelületet.' : $salon->tagline }}"><meta name="referrer" content="no-referrer">@if($salon->demo)<meta name="robots" content="noindex, nofollow">@endif
    <title>@yield('title', $salon->name) · {{ $isDemoHub ? 'SzalonPRO' : $salon->name }}</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/branding/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('salon.css').'?v='.substr(hash_file('sha256', public_path('salon.css')), 0, 12) }}"><link rel="stylesheet" href="{{ asset('premium.css').'?v='.substr(hash_file('sha256', public_path('premium.css')), 0, 12) }}"><link rel="stylesheet" href="{{ asset('industry.css').'?v='.substr(hash_file('sha256', public_path('industry.css')), 0, 12) }}">
    <link rel="stylesheet" href="{{ asset('localized-fields.css').'?v='.substr(hash_file('sha256', public_path('localized-fields.css')), 0, 12) }}">
<link rel="stylesheet" href="{{ asset('booking-experience.css').'?v='.substr(hash_file('sha256', public_path('booking-experience.css')), 0, 12) }}"><link rel="stylesheet" href="{{ asset('mobile-experience.css').'?v='.substr(hash_file('sha256', public_path('mobile-experience.css')), 0, 12) }}">@if($salon->demo)<link rel="stylesheet" href="{{ asset('demo.css').'?v='.substr(hash_file('sha256', public_path('demo.css')), 0, 12) }}">@endif</head>
<body class="public premium {{ $brand['theme'] }} font-{{ $brand['font'] }} layout-{{ $brand['layout'] }} {{ request()->routeIs('home') ? 'public-home' : 'public-inner' }} {{ request()->routeIs('login') ? 'login-page' : '' }}{{ request()->routeIs('booking.*') ? ' booking-page' : '' }}{{ $isDemoHub ? ' demo-hub' : '' }}"
      style="--accent:{{ $brand['accent'] }};--on-accent:{{ $brand['on_accent'] }};--paper:{{ $brand['paper'] }};--ink:{{ $brand['ink'] }};--muted:{{ $brand['muted'] }};--soft:{{ $brand['surface'] }};--line:{{ $brand['line'] }};--radius:{{ $brand['radius'] }}px">
<a class="skip" href="#main">Ugrás a tartalomra</a>
@unless(request()->routeIs('login'))
@if($salon->demo && !$isDemoHub && request()->routeIs('home') && count($demoUrls) === 3)
<div class="demo-strip"><div class="wrap demo-strip-inner">
    <span class="demo-strip-label"><span class="demo-strip-desktop">SzalonPRO · három külön szakági bemutató</span><span class="demo-strip-mobile">{{ request()->routeIs('booking.*') ? 'DEMÓ' : '3 külön demó · saját adatokkal' }}</span></span>
    <nav class="demo-style-nav" aria-label="Szakági demó kiválasztása; mindegyik külön szolgáltatásokkal és foglalási adatokkal működik">
        @foreach(['salon' => 'Fodrászat', 'barber' => 'Barber', 'nails' => 'Körmös'] as $style => $label)
            <a href="{{ $demoUrls[$style] }}" @if($brand['theme'] === $style) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
</div></div>
@elseif($salon->demo && !$isDemoHub)
<div class="demo-flow-bar wrap"><span>Bemutató · {{ $brand['label'] }}</span><a href="{{ route('home') }}">Vissza a bemutatóhoz <x-icon name="arrow-right" /></a></div>
@endif
<header class="public-header">
    <div class="header-inner wrap">
        <a class="brand" href="{{ $isDemoHub ? route('home') : $demoGuestHomeUrl }}">
            <img class="brand-logo {{ $brand['custom_logo'] ? 'custom-logo' : 'default-logo' }}" src="{{ $brand['logo'] }}" alt="" width="44" height="44">
            <span>{{ $isDemoHub ? 'SzalonPRO' : $salon->name }}</span>
        </a>
        <nav class="desktop-nav" aria-label="Főmenü">
            @if($isDemoHub)
            <a href="#szakagak">Szakági demók</a><a href="#napi-munka">Napi munka</a><a href="#sajat-arculat">Saját arculat</a><a class="button" href="#kapcsolat">Beszéljünk a szalonodról <x-icon name="arrow-right" /></a>
            @else
            <a href="{{ $demoGuestHomeUrl }}#szolgaltatasok">Szolgáltatások</a><a href="{{ $demoGuestHomeUrl }}#rolunk">Rólunk</a>@if($salon->demo)<a href="{{ $demoOverviewUrl }}">A rendszer</a>@endif<a href="{{ $demoGuestHomeUrl }}#kapcsolat">Kapcsolat</a>
            <a class="button" href="{{ route('booking.form') }}">{{ $brand['cta_label'] }} <x-icon name="arrow-right" /></a>
            @endif
        </nav>
        <details class="mobile-menu"><summary aria-label="Menü"><x-icon name="list" /><span>Menü</span></summary>
            <nav aria-label="Mobil főmenü">@if($isDemoHub)<a href="#szakagak">Szakági demók</a><a href="#napi-munka">Tulajdonosi bemutató</a><a href="#sajat-arculat">Saját arculat</a><a href="#kapcsolat">Kapcsolat</a>@else<a href="{{ $demoGuestHomeUrl }}#szolgaltatasok">Szolgáltatások</a><a href="{{ $demoGuestHomeUrl }}#rolunk">Rólunk</a>@if($salon->demo)<a href="{{ $demoOverviewUrl }}">A rendszer</a>@endif<a href="{{ $demoGuestHomeUrl }}#kapcsolat">Kapcsolat</a>@endif<a href="{{ route('booking.form') }}">{{ $isDemoHub ? 'Vendégként kipróbálom' : 'Időpontfoglalás' }}</a></nav>
        </details>
    </div>
</header>
@endunless
<main id="main">
    @include('components.flash')
    @if($demoViewer)
    <aside class="demo-viewer-banner wrap"><div><strong>Most az olvasó demófiókot használod.</strong><p>A vendégfoglalás kipróbálásához lépj át a vendégoldalra.</p></div><form method="post" action="{{ route('logout') }}">@csrf<input type="hidden" name="return_to" value="booking"><button class="button secondary">Vendégként kipróbálom <x-icon name="arrow-right" /></button></form></aside>
    @endif
    @yield('content')
</main>
@unless(request()->routeIs('login'))
<footer class="public-footer wrap">
    <div><a class="brand footer-brand" href="{{ $isDemoHub ? route('home') : $demoGuestHomeUrl }}"><img class="brand-logo {{ $brand['custom_logo'] ? 'custom-logo' : 'default-logo' }}" src="{{ $brand['logo'] }}" alt="" width="40" height="40"><span>{{ $isDemoHub ? 'SzalonPRO' : $salon->name }}</span></a><p>{{ $isDemoHub ? 'Vendégfoglalás és napi szalonkezelés, a saját arculatoddal.' : $salon->tagline }}</p></div>
    <div><a href="{{ route('privacy') }}">Adatkezelés</a><a href="{{ route('login') }}">Munkatársi belépés</a><span class="muted">SzalonPRO</span></div>
</footer>
@endunless
@unless(request()->routeIs('booking.*', 'waitlist.*', 'vouchers.*', 'login', 'privacy', 'password.*'))
<div class="mobile-booking-bar"><span>{{ $isDemoHub ? 'A te szakmád.' : ($salon->demo ? $brand['demo_copy']['mobile_cta'] : 'Egy kis idő magadra.') }}</span><a class="button" href="{{ $isDemoHub ? '#szakagak' : route('booking.form') }}">{{ $isDemoHub ? 'Megnézem a demókat' : $brand['cta_label'] }} <x-icon name="arrow-right" /></a></div>
@endunless
<script src="{{ asset('localized-fields.js').'?v='.substr(hash_file('sha256', public_path('localized-fields.js')), 0, 12) }}" defer></script>
<script src="{{ asset('salon.js').'?v='.substr(hash_file('sha256', public_path('salon.js')), 0, 12) }}" defer></script>
@if(request()->routeIs('booking.form', 'booking.rebook.form', 'booking.manage'))<script src="{{ asset('public-booking-refresh.js').'?v='.substr(hash_file('sha256', public_path('public-booking-refresh.js')), 0, 12) }}" defer></script>@endif
<script src="{{ asset('booking-experience.js').'?v='.substr(hash_file('sha256', public_path('booking-experience.js')), 0, 12) }}" defer></script>
<script src="{{ asset('staff-picker.js').'?v='.substr(hash_file('sha256', public_path('staff-picker.js')), 0, 12) }}" defer></script>
<script src="{{ asset('booking-series.js').'?v='.substr(hash_file('sha256', public_path('booking-series.js')), 0, 12) }}" defer></script>
<script src="{{ asset('mobile-experience.js').'?v='.substr(hash_file('sha256', public_path('mobile-experience.js')), 0, 12) }}" defer></script>
@if(request()->routeIs('booking.success'))<script src="{{ asset('booking-link-copy.js').'?v='.substr(hash_file('sha256', public_path('booking-link-copy.js')), 0, 12) }}" defer></script>@endif
</body></html>
