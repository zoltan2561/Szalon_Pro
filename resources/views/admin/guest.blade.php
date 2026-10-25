@extends('layouts.admin')
@section('title','Vendégkarton')
@section('guest-policy')
@if($salon->featureEnabled('no_show_protection'))<p class="notice">Nem jelent meg: <strong>{{ $noShowCount }}</strong> alkalom. A számláló a számodra látható foglalásokat mutatja.</p>@endif
@endsection
@section('content')<div class="page-heading"><div><p class="eyebrow">VENDÉGKARTON #{{ $guest->id }}</p><h1>{{ $guest->name }}</h1><p>{{ $guest->email ?: 'Nincs e-mail' }}{{ $guest->phone ? ' · '.$guest->phone : '' }}</p></div>@unless($guest->anonymized_at)<a class="button" href="{{ route('admin.create',['guest_id'=>$guest->id,'service_id'=>$bookings->first()?->service_id,'staff_id'=>$bookings->first()?->staff_id]) }}">+ Újrafoglalás</a>
@endunless
</div>@if($duplicates)<div class="notice">{{ $duplicates }} másik kartonon egyező elérhetőség szerepel. A rendszer nem vonja össze őket; közös elérhetőség több személyhez is tartozhat.</div>
@endif

@yield('guest-policy')
@if(!$guest->anonymized_at && (auth()->user()->role==='owner' || auth()->user()->can_manage_bookings))
<details class="panel"><summary>Kapcsolati adatok módosítása</summary><form method="post" action="{{ route('admin.contact',$guest) }}">@csrf
<label>Név<input name="name" value="{{ old('name',$guest->name) }}" required maxlength="120"></label>
<div class="form-grid"><label>E-mail-cím <span class="muted">(opcionális)</span><input name="email" type="email" value="{{ old('email',$guest->email) }}"></label><label>Telefon<input name="phone" value="{{ old('phone',$guest->phone) }}" maxlength="40"></label></div>
<button class="button secondary">Kapcsolati adatok mentése</button></form></details>
@endif
<div class="panel"><h2>Látogatási előzmények és következő alkalmak</h2>@foreach($bookings as $booking)<div class="history-row"><div><strong>{{ $booking->starts_at->setTimezone($salon->timezone)->format('Y. m. d. H:i') }}</strong><p>{{ $booking->service_name }} · {{ $booking->staff->name }}@if($salon->featureEnabled('financials')) · {{ number_format($booking->price,0,',',' ') }} Ft @endif</p></div><span class="badge {{ $booking->status }}">{{ $booking->label() }}</span><a href="{{ route('admin.booking',$booking) }}">Megnyitás ↗</a></div>
@endforeach
</div>
@if(auth()->user()->role==='owner' && ($salon->featureEnabled('passes') || $salon->featureEnabled('loyalty')))@include('admin.guest-benefits')@endif
@if(auth()->user()->role==='owner' || auth()->user()->can_view_notes)<form class="panel" method="post" action="{{ route('admin.notes',$guest) }}">@csrf<h2>Szakmai megjegyzés</h2><p class="muted">Csak a szolgáltatáshoz szükséges megjegyzést rögzíts. Egészségügyi vagy más érzékeny adatot ne írj ide.</p><label>Megjegyzés<textarea name="notes" rows="4" maxlength="3000">{{ old('notes',$guest->notes) }}</textarea></label><button class="button secondary">Megjegyzés mentése</button></form>
@endif

@if(auth()->user()->role==='owner')<details class="panel"><summary>Adatkezelési műveletek</summary><p>Megőrzési irányelv: {{ $salon->retention_days }} nap. A törlést az admin felülvizsgálat után indítja; a rendszer nem töröl automatikusan vendégelőzményt.</p><a class="button secondary" href="{{ route('admin.export',$guest) }}">Vendégadatok exportálása</a><hr><form method="post" action="{{ route('admin.anonymize',$guest) }}">@csrf<p>A személyes adatok és a megjegyzés törlődik, a látogatások névtelen összesítésként megmaradnak. Jövőbeli foglalás esetén előbb azt kell rendezni.</p><label>Jelenlegi jelszavad<input type="password" name="password" autocomplete="current-password" required></label><label class="check"><input type="checkbox" name="confirm" value="1" required><span>Kérem a személyes adatok végleges törlését.</span></label><button class="button danger">Személyes adatok törlése</button></form></details>
@endif


@endsection
