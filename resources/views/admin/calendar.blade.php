@extends('layouts.admin')
@section('title',request()->routeIs('admin.today') ? 'Mai nap':'Naptár')
@section('content')
<div class="page-heading"><div><p class="eyebrow">{{ $day->locale('hu')->isoFormat('YYYY. MMMM D., dddd') }}</p><h1>{{ request()->routeIs('admin.today') ? 'Egy új nap. Jó munkát!':'A szalon naptára' }}</h1><p class="muted">Minden időpont egy helyen, hogy a vendégeidre figyelhess.</p></div>@if(auth()->user()->role==='owner' || auth()->user()->can_manage_bookings)<a class="button" href="{{ route('admin.create') }}">+ Új foglalás</a>
@endif
</div>
@if(!$salon->published && auth()->user()->role==='owner')<div class="notice">A nyilvános oldal még nincs közzétéve. <a href="{{ route('admin.settings') }}">Induló beállítások →</a></div>
@endif

@if($failed)<div class="notice error">{{ $failed }} értesítés küldése figyelmet igényel. <a href="{{ route('admin.notifications') }}">Küldési napló →</a></div>
@endif

@if(request()->routeIs('admin.today') && $next)<section class="next-guest"><div><p class="eyebrow">A KÖVETKEZŐ VENDÉGED</p><h2>{{ $next->guest->name }}</h2><p>{{ $next->service_name }} · {{ $next->staff->name }}</p><a href="{{ route('admin.booking',$next) }}">Foglalás megnyitása ↗</a></div><div class="next-time">{{ $next->starts_at->setTimezone($salon->timezone)->format('H:i') }}<small>{{ $next->starts_at->setTimezone($salon->timezone)->format('m. d.') }} · {{ $next->duration_minutes }} perc</small></div></section>
@endif

<div class="stats"><article><span>Időpontok</span><strong>{{ $bookings->count() }}</strong><small>{{ $week ? 'a kiválasztott héten':'a kiválasztott napon' }}</small></article><article><span>Elvégezve</span><strong>{{ $bookings->where('status','completed')->count() }}</strong><small>{{ $bookings->where('status','cancelled')->count() }} lemondás · {{ $bookings->where('status','no_show')->count() }} meg nem jelenés</small></article><article><span>Elvégzett szolgáltatások összege</span><strong>{{ number_format($bookings->where('status','completed')->sum('actual_amount'),0,',',' ') }} <small>Ft</small></strong><small>Rögzített összeg, nem pénzforgalmi adat</small></article></div>
<section class="panel calendar-panel"><div class="section-heading"><div><p class="eyebrow">A NAP MENETE</p><h2>{{ $week ? 'Heti áttekintés':'Időpontok időrendben' }}</h2></div><form class="inline-form" method="get"><label class="sr-only" for="calendar-date">Naptár napja</label><input id="calendar-date" type="date" name="date" value="{{ $date }}"><label class="sr-only" for="calendar-view">Nézet</label><select id="calendar-view" name="view"><option value="day">Napi nézet</option><option value="week" @selected($week)>Heti nézet</option></select><button class="button secondary small">Mutasd</button></form></div>
@if($week)<div class="week-grid">@foreach(range(0,6) as $offset)@php($d=$day->addDays($offset))<div class="week-day"><h3>{{ $d->locale('hu')->isoFormat('ddd, D.') }}</h3>@foreach($bookings->filter(fn($b)=>$b->starts_at->setTimezone($salon->timezone)->toDateString()===$d->toDateString()) as $booking)<a class="week-booking {{ $booking->status }}" href="{{ route('admin.booking',$booking) }}"><strong>{{ $booking->starts_at->setTimezone($salon->timezone)->format('H:i') }}</strong><span>{{ $booking->guest->name }}</span><small>{{ $booking->service_name }} · {{ $booking->label() }}</small></a>
@endforeach
</div>
@endforeach
</div>

@else
 @include('admin.booking-list')
@endif

<div class="calendar-foot"><span>{{ $salon->timezone }} · Online és kézi foglalások</span><a href="{{ route('admin.create',['date'=>$date]) }}">Szabad időpontok keresése ↗</a></div></section>

@endsection
