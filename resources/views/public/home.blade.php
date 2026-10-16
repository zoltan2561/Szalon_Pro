@extends('layouts.public')
@section('title','Kezdőlap')
@section('content')
<section class="hero wrap"><div><p class="eyebrow">SZÉPSÉG · FIGYELEM · FELTÖLTŐDÉS</p><h1>{{ $salon->tagline }}</h1><p class="hero-copy">{{ $salon->about }}</p><a class="button" href="{{ route('booking.form') }}">Találd meg a következő időpontod <span aria-hidden="true">↗</span></a><p class="hero-note">Átlátható árak · Egyszerű online foglalás</p></div><div class="hero-art" aria-hidden="true"><div class="art-orbit"></div><div class="art-arch"><span>{{ mb_substr($salon->name,0,1) }}</span><i></i></div><div class="art-caption">EGY KIS IDŐ.<br>CSAK NEKED.</div><span class="art-star">✳</span></div></section>
<section class="services-section wrap" id="szolgaltatasok"><div class="section-heading"><div><p class="eyebrow">AMIBEN SEGÍTHETÜNK</p><h2>Szolgáltatások és árak</h2></div><p>Válassz, és nézd meg a szabad időpontokat.</p></div><div class="service-list">@forelse($services as $service)<article class="service-row"><div class="service-number">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</div><div><small class="eyebrow">{{ $service->category }}</small><h3>{{ $service->name }}</h3><p>{{ $service->description }}</p></div><div class="service-meta"><strong>{{ number_format($service->price,0,',',' ') }} Ft{{ $service->price_kind==='from' ? '-tól':'' }}</strong><span>{{ $service->duration_minutes }} perc{{ $service->price_kind==='indicative' ? ' · tájékoztató ár':'' }}</span></div><a class="round-link" aria-label="{{ $service->name }} foglalása" href="{{ route('booking.form',['service_id'=>$service->id]) }}">↗</a></article>
@empty
<p class="empty">A szolgáltatások hamarosan elérhetők.</p>
@endforelse
</div></section>
<section class="team-section wrap"><div class="section-heading"><div><p class="eyebrow">SZEMÉLYES FIGYELEM</p><h2>Akik várnak rád</h2></div></div><div class="team-grid">@foreach($staff as $person)<article class="team-card"><span class="avatar" aria-hidden="true">{{ mb_substr($person->name,0,1) }}</span><div><h3>{{ $person->name }}</h3><p class="eyebrow">{{ $person->title }}</p><p>{{ $person->bio }}</p><a href="{{ route('booking.form',['staff_id'=>$person->id]) }}">Időpontot választok ↗</a></div></article>
@endforeach
</div></section>
@if($salon->gallery)<section class="wrap"><p class="eyebrow">A SZALON ÉS A MUNKÁINK</p><h2>Egy pillantás hozzánk</h2><div class="gallery">@foreach($salon->gallery as $picture)<img src="{{ Storage::url($picture) }}" alt="{{ $salon->name }} – referencia {{ $loop->iteration }}" loading="lazy">
@endforeach
</div></section>
@endif

<section class="contact-section wrap" id="kapcsolat"><div><p class="eyebrow">TALÁLKOZZUNK</p><h2>Várunk szeretettel.</h2><p>{{ $salon->address }}</p><a href="tel:{{ preg_replace('/[^+0-9]/','',$salon->phone ?? '') }}">{{ $salon->phone }}</a><br><a href="mailto:{{ $salon->email }}">{{ $salon->email }}</a></div><div><h3>Nyitvatartás</h3>@foreach([1=>'Hétfő',2=>'Kedd',3=>'Szerda',4=>'Csütörtök',5=>'Péntek',6=>'Szombat',7=>'Vasárnap'] as $day=>$label)<div class="opening-line"><span>{{ $label }}</span><span>{{ collect($salon->opening_hours[$day] ?? [])->map(fn($r)=>$r[0].'–'.$r[1])->join(', ') ?: 'Zárva' }}</span></div>
@endforeach
</div><div><h3>Foglalási feltételek</h3><p class="preserve">{{ $salon->terms }}</p><a class="button" href="{{ route('booking.form') }}">Időpontot foglalok ↗</a></div></section>

@endsection
