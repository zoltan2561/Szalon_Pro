<!DOCTYPE html>
<html lang="hu">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>@yield('title','Mai nap') · SzalonPRO</title><link rel="icon" href="{{ asset('assets/branding/favicon.png') }}"><link rel="stylesheet" href="{{ asset('salon.css').'?v='.substr(hash_file('sha256', public_path('salon.css')), 0, 12) }}"><link rel="stylesheet" href="{{ asset('premium.css').'?v='.substr(hash_file('sha256', public_path('premium.css')), 0, 12) }}"></head>
<body class="admin premium-admin">
<a class="skip" href="#main">Ugrás a tartalomra</a>
<aside class="sidebar">
<a class="brand" href="{{ route('admin.today') }}"><span class="brand-mark"><img src="{{ asset('assets/branding/sp-mark.png') }}" alt="" width="31" height="31"></span><span>Szalon<span class="muted">PRO</span></span></a>
<p class="workspace-label">{{ $salon->name }}</p>
<nav aria-label="Munkafelület">
@foreach(['today'=>['clock','Mai nap'],'calendar'=>['calendar-blank','Naptár'],'guests'=>['users','Vendégeim']] as $key=>$item)
<a class="{{ request()->routeIs('admin.'.$key) ? 'active':'' }}" href="{{ route('admin.'.$key) }}" @if(request()->routeIs('admin.'.$key)) aria-current="page" @endif><x-icon :name="$item[0]" />{{ $item[1] }}</a>
@endforeach
@include('components.admin-links')
</nav>
<div class="sidebar-bottom">
<a href="{{ route('home') }}" target="_blank" rel="noopener">Weboldal előnézete ↗</a>
<p>{{ auth()->user()->name }}<small>{{ auth()->user()->role==='owner' ? 'Tulajdonos':'Munkatárs' }}</small></p>
<form method="post" action="{{ route('logout') }}">@csrf<button class="text-button">Kijelentkezés</button></form>
</div>
</aside>
<div class="admin-shell">
<header class="admin-top">
<span class="eyebrow">A SZALONOD, ÁTLÁTHATÓAN</span>
<div><span class="live-dot" aria-hidden="true"></span>{{ $salon->demo ? 'Bemutató környezet':'Munkafelület' }}</div>
<form class="mobile-logout" method="post" action="{{ route('logout') }}">@csrf<button class="text-button">Kilépés</button></form>
</header>
<main id="main" class="admin-content">@include('components.flash')
@if(!empty($operationWarnings))<details class="notice compact-warning" aria-label="Működési figyelmeztetések"><summary>Figyelem · {{ count($operationWarnings) }} probléma</summary><ul>@foreach($operationWarnings as $warning)<li>{{ $warning }}</li>@endforeach</ul><a href="{{ route('admin.notifications') }}">Értesítések és feldolgozási állapot →</a></details>@endif
@yield('content')</main>
<footer class="admin-footer">SzalonPRO · {{ config('salon.version') }} <span>Kevesebb szervezés. Több figyelem.</span></footer>
</div>
<nav class="mobile-admin-tabs" aria-label="Mobil munkafelület">
@foreach(['today'=>['clock','Ma'],'calendar'=>['calendar-blank','Naptár']] as $key=>$item)
<a href="{{ route('admin.'.$key) }}" @if(request()->routeIs('admin.'.$key)) aria-current="page" @endif><x-icon :name="$item[0]" /><span>{{ $item[1] }}</span></a>
@endforeach
@if(auth()->user()->role === 'owner' || auth()->user()->can_manage_bookings)<a class="mobile-new-booking" href="{{ route('admin.create') }}" aria-label="Új foglalás" @if(request()->routeIs('admin.create','admin.booking*')) aria-current="page" @endif><x-icon name="calendar-blank" /><span>+ Új</span></a>@else<span class="mobile-tab-disabled" aria-label="Új foglaláshoz nincs jogosultság">Új foglalás</span>@endif
<a href="{{ route('admin.guests') }}" @if(request()->routeIs('admin.guests','admin.guest')) aria-current="page" @endif><x-icon name="users" /><span>Vendégeim</span></a>
<button type="button" data-open-more aria-controls="admin-more-menu" aria-expanded="false" @if(!request()->routeIs('admin.today','admin.calendar','admin.guests','admin.guest','admin.create','admin.booking*')) aria-current="page" @endif><x-icon name="list" /><span>Több</span></button>
</nav>
<dialog id="admin-more-menu" class="admin-more-sheet" aria-labelledby="admin-more-title"><div class="section-heading"><h2 id="admin-more-title">További lehetőségek</h2><form method="dialog"><button class="button secondary" aria-label="Menü bezárása">Bezárás</button></form></div><nav aria-label="További menüpontok">@include('components.admin-links')<a href="{{ route('home') }}">Weboldal előnézete ↗</a></nav><form method="post" action="{{ route('logout') }}">@csrf<button class="button secondary full">Kijelentkezés</button></form></dialog>
<script src="{{ asset('salon.js').'?v='.substr(hash_file('sha256', public_path('salon.js')), 0, 12) }}" defer></script>
</body></html>
