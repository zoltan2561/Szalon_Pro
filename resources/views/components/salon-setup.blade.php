@php
    $setupSalon = $salon ?? \App\Models\Salon::current();
    $setupSteps = app(\App\Services\SalonSetup::class)->steps($setupSalon);
@endphp
@if(collect($setupSteps)->contains('complete', false))
<section class="{{ ($filament ?? false) ? 'fi-section' : 'panel' }}" aria-labelledby="salon-setup-heading" style="padding:1.5rem">
    <h2 id="salon-setup-heading" style="font-size:1.125rem;font-weight:600;margin-bottom:.5rem">A szalonod indulási teendői</h2>
    <p>A saját adataiddal töltsd ki az alábbiakat. Ha egyedül dolgozol, magadat is vedd fel munkatársként. A Google Naptár később is összeköthető.</p>
    <ul style="list-style:none;padding:0;margin:1rem 0;display:grid;gap:.75rem">
        @foreach($setupSteps as $step)
        <li><span aria-label="{{ $step['complete'] ? 'Kész' : 'Kitöltendő' }}">{{ $step['complete'] ? '✓' : '○' }}</span> <a href="{{ $step['url'] }}" style="text-decoration:underline">{{ $step['label'] }}</a></li>
        @endforeach
    </ul>
    <p>A szolgáltatások, munkarend és feltételek beállításáig az online foglalás még nem indul el. Az e-mail-küldés bekapcsolásához kérd az üzemeltető segítségét.</p>
</section>
@endif
