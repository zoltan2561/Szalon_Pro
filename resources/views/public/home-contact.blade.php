<section class="contact-section" id="kapcsolat"><div class="contact-inner wrap"><div><p class="eyebrow">TALÁLKOZZUNK</p><h2>{{ $salon->demo ? $brand['demo_copy']['contact_heading'] : 'Várunk szeretettel.' }}</h2><p class="contact-line"><x-icon name="map-pin" />{{ $salon->address }}</p>
    @if($salon->demo)<p class="demo-contact-note">A címet, telefonszámot és e-mail-címet minden szalon a saját adataira cserélheti. Ez a bemutató nem valódi szalon.</p>
    @else
    @if($salon->phone)<a class="contact-line" href="tel:{{ preg_replace('/[^+0-9]/','',$salon->phone) }}"><x-icon name="phone" />{{ $salon->phone }}</a>@endif
    @if($salon->email)<a class="contact-line" href="mailto:{{ $salon->email }}"><x-icon name="envelope-simple" />{{ $salon->email }}</a>@endif
    @endif
    </div><div><h3>Nyitvatartás</h3>@foreach([1=>'Hétfő',2=>'Kedd',3=>'Szerda',4=>'Csütörtök',5=>'Péntek',6=>'Szombat',7=>'Vasárnap'] as $day=>$label)<div class="opening-line"><span>{{ $label }}</span><span>{{ collect($salon->opening_hours[$day] ?? [])->map(fn($r)=>$r[0].'–'.$r[1])->join(', ') ?: 'Zárva' }}</span></div>@endforeach</div><div><h3>{{ $salon->demo ? $brand['demo_copy']['contact_cta_heading'] : 'A következő énidőd.' }}</h3><p class="preserve">{{ $salon->terms }}</p><a class="button" href="{{ route('booking.form') }}">{{ $brand['cta_label'] }} <x-icon name="arrow-right" /></a></div></div></section>
