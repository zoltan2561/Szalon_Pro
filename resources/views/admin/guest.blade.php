@extends('layouts.admin')
@section('title','Vendég adatlapja')
@section('guest-policy')
@if($salon->featureEnabled('no_show_protection'))<p class="{{ $noShowCount ? 'notice' : 'muted' }}">Nem jelent meg: <strong>{{ $noShowCount }}</strong> alkalom. A számláló a számodra látható foglalásokat mutatja.</p>@endif
@endsection
@section('content')<div class="page-heading"><div><p class="eyebrow">VENDÉG ADATLAPJA #{{ $guest->id }}</p><h1>{{ $guest->name }}</h1><div class="guest-contact-actions">@if(!$demoViewer && $guest->phone)<a class="button secondary" href="tel:{{ preg_replace('/[^+0-9]/', '', $guest->phone) }}">Vendég hívása</a>@endif @if(!$demoViewer && $guest->email)<a href="mailto:{{ $guest->email }}">{{ $guest->email }}</a>@endif</div></div>@if(! $guest->anonymized_at && (auth()->user()->role === 'owner' || auth()->user()->can_manage_bookings))<a class="button" href="{{ route('admin.create',(($lastVisit ?? $upcomingBookings->first() ?? $bookings->first())?->rebookingParameters() ?? ['guest_id' => $guest->id])) }}">+ Újrafoglalás</a>
@endif
</div>@if($duplicates)<div class="notice">{{ $duplicates }} másik kartonon egyező elérhetőség szerepel. A rendszer nem vonja össze őket; közös elérhetőség több személyhez is tartozhat.</div>
@endif

@yield('guest-policy')
@if(auth()->user()->canViewGuestNotes())<section class="panel guest-professional-notes"><h2>Szakmai megjegyzés</h2><p class="preserve">{{ $guest->notes ?: 'Még nincs szakmai megjegyzés.' }}</p>@unless($guest->anonymized_at)<details @if($errors->has('notes')) open @endif><summary>Megjegyzés szerkesztése</summary><form method="post" action="{{ route('admin.notes',$guest) }}">@csrf<p class="muted">Csak a szolgáltatáshoz szükséges megjegyzést rögzíts. Egészségügyi vagy más érzékeny adatot ne írj ide.</p><label>Megjegyzés<textarea name="notes" rows="3" maxlength="3000">{{ old('notes',$guest->notes) }}</textarea></label><button class="button secondary">Megjegyzés mentése</button></form></details>@endunless</section>@endif
<section class="panel"><h2>Következő alkalmak</h2>@include('components.guest-appointments', ['appointments' => $upcomingBookings, 'emptyMessage' => 'Nincs közelgő foglalás. Az Újrafoglalás gombbal új időpontot kereshetsz.'])</section>
@if(!$guest->anonymized_at && (auth()->user()->role==='owner' || auth()->user()->can_manage_bookings))
<details class="panel"><summary>Kapcsolati adatok módosítása</summary><form method="post" action="{{ route('admin.contact',$guest) }}">@csrf
<label>Név<input name="name" value="{{ old('name',$guest->name) }}" required maxlength="120"></label>
<div class="form-grid"><label>E-mail-cím <span class="muted">(opcionális)</span><input name="email" type="email" value="{{ old('email',$guest->email) }}"></label><label>Telefon<input name="phone" value="{{ old('phone',$guest->phone) }}" maxlength="40"></label></div>
<button class="button secondary">Kapcsolati adatok mentése</button></form></details>
@endif
<details class="panel guest-history"><summary>Látogatási előzmények · {{ $pastBookings->count() }}</summary>@include('components.guest-appointments', ['appointments' => $pastBookings, 'emptyMessage' => 'Még nincs lezárt látogatás.'])</details>
@if(!$demoViewer && auth()->user()->role==='owner' && ($salon->featureEnabled('passes') || $salon->featureEnabled('loyalty')))@include('admin.guest-benefits')@endif

@if(!$demoViewer && auth()->user()->role==='owner')<details class="panel"><summary>Adatkezelési műveletek</summary><p>Megőrzési irányelv: {{ $salon->retention_days }} nap. A törlést az admin felülvizsgálat után indítja; a rendszer nem töröl automatikusan vendégelőzményt.</p><a class="button secondary" href="{{ route('admin.export',$guest) }}">Vendégadatok exportálása</a><hr><form method="post" action="{{ route('admin.anonymize',$guest) }}">@csrf<p>A személyes adatok és a megjegyzés törlődik, a látogatások névtelen összesítésként megmaradnak. Jövőbeli foglalás esetén előbb azt kell rendezni.</p><label>Jelenlegi jelszavad<input type="password" name="password" autocomplete="current-password" required></label><label class="check"><input type="checkbox" name="confirm" value="1" required><span>Kérem a személyes adatok végleges törlését.</span></label><button class="button danger">Személyes adatok törlése</button></form></details>
@endif


@endsection
