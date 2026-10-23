@extends('layouts.admin')
@section('title', 'Bérletek és hűségpontok')
@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">VISSZATÉRŐ VENDÉGEK</p>
        <h1>Bérletek és hűségpontok</h1>
        <p class="muted">A szabályokat itt alakíthatod. A modulokat a Modulok oldalon külön kapcsolhatod be.</p>
    </div>
    <a class="button secondary" href="{{ route('admin.modules.index') }}">Modulok beállítása</a>
</div>

<div class="form-grid">
    <section class="panel">
        <p class="eyebrow">BÉRLETEK</p>
        <h2>{{ $salon->featureEnabled('passes') ? 'Bekapcsolva' : 'Kikapcsolva' }}</h2>
        <p class="muted">Egy bérlet egy kiválasztott szolgáltatáshoz tartozik. A kiadás és a felhasználás látogatási alkalmakat követ; fizetést nem kezel.</p>
    </section>
    <section class="panel">
        <p class="eyebrow">HŰSÉGPONTOK</p>
        <h2>{{ $salon->featureEnabled('loyalty') ? 'Bekapcsolva' : 'Kikapcsolva' }}</h2>
        <p class="muted">Pontot csak az elvégzett látogatás ír jóvá. A kikapcsolás a már megszerzett pontokat megőrzi.</p>
    </section>
</div>

<section class="panel">
    <p class="eyebrow">HŰSÉGPROGRAM</p>
    <h2>Egyszerű, érthető szabályok</h2>
    <form method="post" action="{{ route('admin.benefits.settings') }}">
        @csrf
        <div class="form-grid three">
            <label>Alap pont / elvégzett látogatás<input type="number" name="loyalty_points_per_visit" min="0" max="100" required value="{{ old('loyalty_points_per_visit', $settings->loyalty_points_per_visit) }}"></label>
            <label>Jutalomhoz szükséges pont<input type="number" name="loyalty_reward_threshold" min="1" max="1000" required value="{{ old('loyalty_reward_threshold', $settings->loyalty_reward_threshold) }}"></label>
            <label>Jutalom neve<input name="loyalty_reward_label" maxlength="120" required value="{{ old('loyalty_reward_label', $settings->loyalty_reward_label) }}"></label>
        </div>
        <p class="muted">A jutalom beváltását a vendégkartonon rögzítheted. A rendszer nem módosít árat és nem indít fizetést.</p>
        <button class="button">Hűségszabály mentése</button>
    </form>
</section>

<section class="panel">
    <p class="eyebrow">SZOLGÁLTATÁSONKÉNT</p>
    <h2>Eltérő pontszámok</h2>
    <p class="muted">Az üres mező az alap pontszámot használja. A 0 ponttal kizárhatod a szolgáltatást a jóváírásból.</p>
    @foreach($services as $service)
        <form method="post" action="{{ route('admin.benefits.points', $service) }}" class="inline-form">
            @csrf
            <label>{{ $service->name }}<input name="loyalty_points" type="number" min="0" max="100" placeholder="Alap" value="{{ $service->loyalty_points }}"></label>
            <button class="button secondary small">Pontszám mentése</button>
        </form>
        @unless($loop->last)<hr>@endunless
    @endforeach
</section>

<section class="panel">
    <p class="eyebrow">BÉRLETTÍPUSOK</p>
    <h2>Ajánlatok alkalmakban</h2>
    <p class="muted">A már kiadott bérletek szolgáltatása, alkalomszáma és lejárata a későbbi módosításoktól független.</p>
    @foreach($plans->concat([new \App\Models\PassPlan]) as $plan)
        <details class="panel" @unless($plan->exists) open @endunless>
            <summary>{{ $plan->exists ? $plan->name.' · '.($plan->active ? 'Aktív' : 'Archivált') : '+ Új bérlettípus' }}</summary>
            <form method="post" action="{{ route('admin.benefits.plan', $plan->exists ? $plan : null) }}">
                @csrf
                <div class="form-grid">
                    <label>Név<input name="name" maxlength="120" required value="{{ $plan->name }}"></label>
                    <label>Szolgáltatás<select name="service_id" required>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}" @selected($plan->service_id === $service->id)>{{ $service->name }}</option>
                        @endforeach
                    </select></label>
                </div>
                <div class="form-grid">
                    <label>Alkalmak száma<input name="visits_total" type="number" min="2" max="100" required value="{{ $plan->visits_total ?? 5 }}"></label>
                    <label>Érvényesség a kiadástól, napokban (üres = nincs lejárat)<input name="validity_days" type="number" min="1" max="3650" value="{{ $plan->validity_days }}"></label>
                </div>
                <label class="check"><input name="active" type="checkbox" value="1" @checked(!$plan->exists || $plan->active)><span>Kiadható bérlettípus</span></label>
                <button class="button secondary">Bérlettípus mentése</button>
            </form>
        </details>
    @endforeach
</section>
@endsection
