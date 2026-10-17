<section class="studio-section wrap" id="rolunk">
    <div class="studio-image">
        <img src="{{ $brand['studio'] }}" alt="{{ $salon->name }} – részletkép" width="800" height="500" loading="lazy">
        @unless($brand['custom_hero'])<small>AI-generált bemutató részletkép</small>@endunless
    </div>
    <div class="studio-copy">
        <p class="eyebrow">JÓ HELYEN VAGY</p>
        @if($salon->demo)
            <h2>{{ $brand['demo_copy']['studio_heading'] }}</h2>
            <p>{{ $brand['demo_copy']['studio_text'] }}</p>
        @else
            <h2>Rólad szól.<br>A részletekig.</h2>
            <p class="preserve">{{ $salon->about }}</p>
        @endif
        <a class="text-link" href="{{ route('booking.form') }}">Találjuk meg a te időpontod <x-icon name="arrow-right" /></a>
    </div>
</section>
