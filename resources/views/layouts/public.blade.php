<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="{{ $salon->tagline }}"><meta name="referrer" content="no-referrer">@if($salon->demo)<meta name="robots" content="noindex, nofollow">@endif
    <title>@yield('title', $salon->name) · {{ $salon->name }}</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/branding/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('salon.css').'?v='.substr(hash_file('sha256', public_path('salon.css')), 0, 12) }}"><link rel="stylesheet" href="{{ asset('premium.css').'?v='.substr(hash_file('sha256', public_path('premium.css')), 0, 12) }}"><link rel="stylesheet" href="{{ asset('industry.css').'?v='.substr(hash_file('sha256', public_path('industry.css')), 0, 12) }}">
    <link rel="stylesheet" href="{{ asset('localized-fields.css').'?v='.substr(hash_file('sha256', public_path('localized-fields.css')), 0, 12) }}">
</head>
<body class="public premium {{ $brand['theme'] }} font-{{ $brand['font'] }} layout-{{ $brand['layout'] }} {{ request()->routeIs('home') ? 'public-home' : 'public-inner' }} {{ request()->routeIs('login') ? 'login-page' : '' }}"
      style="--accent:{{ $brand['accent'] }};--on-accent:{{ $brand['on_accent'] }};--paper:{{ $brand['paper'] }};--ink:{{ $brand['ink'] }};--muted:{{ $brand['muted'] }};--soft:{{ $brand['surface'] }};--line:{{ $brand['line'] }};--radius:{{ $brand['radius'] }}px">
<a class="skip" href="#main">Ugrás a tartalomra</a>
@unless(request()->routeIs('login'))
@if($salon->demo && count($demoUrls) === 3)
<div class="demo-strip"><div class="wrap demo-strip-inner">
    <span class="demo-strip-label"><span class="demo-strip-desktop">SzalonPRO · három külön szakági bemutató</span><span class="demo-strip-mobile">3 külön demó · saját adatokkal</span></span>
    <nav class="demo-style-nav" aria-label="Szakági demó kiválasztása; mindegyik külön szolgáltatásokkal és foglalási adatokkal működik">
        @foreach(['salon' => 'Fodrászat', 'barber' => 'Barber', 'nails' => 'Körmös'] as $style => $label)
            <a href="{{ $demoUrls[$style] }}" @if($brand['theme'] === $style) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
</div></div>
@endif
<header class="public-header">
    <div class="header-inner wrap">
        <a class="brand" href="{{ route('home') }}">
            <img class="brand-logo {{ $brand['custom_logo'] ? 'custom-logo' : 'default-logo' }}" src="{{ $brand['logo'] }}" alt="" width="44" height="44">
            <span>{{ $salon->name }}</span>
        </a>
        <nav class="desktop-nav" aria-label="Főmenü">
            <a href="{{ route('home') }}#szolgaltatasok">Szolgáltatások</a><a href="{{ route('home') }}#rolunk">Rólunk</a>@if($salon->demo)<a href="{{ route('home') }}#rendszer">A rendszer</a>@endif<a href="{{ route('home') }}#kapcsolat">Kapcsolat</a>
            <a class="button" href="{{ route('booking.form') }}">{{ $brand['cta_label'] }} <x-icon name="arrow-right" /></a>
        </nav>
        <details class="mobile-menu"><summary aria-label="Menü"><x-icon name="list" /><span>Menü</span></summary>
            <nav aria-label="Mobil főmenü"><a href="{{ route('home') }}#szolgaltatasok">Szolgáltatások</a><a href="{{ route('home') }}#rolunk">Rólunk</a>@if($salon->demo)<a href="{{ route('home') }}#rendszer">A rendszer</a>@endif<a href="{{ route('home') }}#kapcsolat">Kapcsolat</a><a href="{{ route('booking.form') }}">Időpontfoglalás</a></nav>
        </details>
    </div>
</header>
@endunless
<main id="main">@include('components.flash')@yield('content')</main>
@unless(request()->routeIs('login'))
<footer class="public-footer wrap">
    <div><a class="brand footer-brand" href="{{ route('home') }}"><img class="brand-logo {{ $brand['custom_logo'] ? 'custom-logo' : 'default-logo' }}" src="{{ $brand['logo'] }}" alt="" width="40" height="40"><span>{{ $salon->name }}</span></a><p>{{ $salon->tagline }}</p></div>
    <div><a href="{{ route('privacy') }}">Adatkezelés</a><a href="{{ route('login') }}">Munkatársi belépés</a><span class="muted">SzalonPRO</span></div>
</footer>
@endunless
@unless(request()->routeIs('booking.*', 'vouchers.*', 'login', 'privacy', 'password.*'))
<div class="mobile-booking-bar"><span>{{ $salon->demo ? $brand['demo_copy']['mobile_cta'] : 'Egy kis idő magadra.' }}</span><a class="button" href="{{ route('booking.form') }}">{{ $brand['cta_label'] }} <x-icon name="arrow-right" /></a></div>
@endunless
<script src="{{ asset('localized-fields.js').'?v='.substr(hash_file('sha256', public_path('localized-fields.js')), 0, 12) }}" defer></script>
<script src="{{ asset('salon.js').'?v='.substr(hash_file('sha256', public_path('salon.js')), 0, 12) }}" defer></script>
@if(request()->routeIs('booking.form', 'booking.rebook.form', 'booking.manage'))<script src="{{ asset('public-booking-refresh.js').'?v='.substr(hash_file('sha256', public_path('public-booking-refresh.js')), 0, 12) }}" defer></script>@endif
</body></html>
