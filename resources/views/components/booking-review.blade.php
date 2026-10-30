<section class="booking-review" data-booking-review data-service="{{ $service?->name }}" data-date="{{ \Carbon\CarbonImmutable::parse($date, $salon->timezone)->locale('hu')->isoFormat('YYYY. MMMM D., dddd') }}" data-price="{{ number_format($service?->price ?? 0, 0, ',', ' ') }} Ft{{ $service?->price_kind === 'from' ? '-tól' : '' }}{{ $service?->price_kind === 'indicative' ? ' · tájékoztató ár' : '' }}" data-duration="{{ $service?->duration_minutes }} perc">
    <h2>Foglalás összesítője</h2>
    <dl>
        <div><dt>Szolgáltatás</dt><dd data-review-service>{{ $service?->name }}</dd></div>
        <div><dt>Nap</dt><dd data-review-date>{{ \Carbon\CarbonImmutable::parse($date, $salon->timezone)->locale('hu')->isoFormat('YYYY. MMMM D., dddd') }}</dd></div>
        <div><dt>Időpont és szakember</dt><dd data-review-slot aria-live="polite">Válassz egy szabad időpontot.</dd></div>
        <div><dt>Időtartam és ár</dt><dd><span data-review-duration>{{ $service?->duration_minutes }} perc</span> · <span data-review-price>{{ number_format($service?->price ?? 0, 0, ',', ' ') }} Ft{{ $service?->price_kind === 'from' ? '-tól' : '' }}{{ $service?->price_kind === 'indicative' ? ' · tájékoztató ár' : '' }}</span></dd></div>
    </dl>
</section>
