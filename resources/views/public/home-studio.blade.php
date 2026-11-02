<section class="studio-section wrap {{ $brand['show_hero'] ? '' : 'studio-without-image' }}" id="rolunk">
    @if($brand['show_hero'])<div class="studio-image">
        <img src="{{ $brand['studio'] }}" alt="{{ $salon->name }} – részletkép" width="800" height="500" loading="lazy">
        @if($salon->demo && !$brand['custom_hero'])<small>AI-generált bemutató részletkép</small>@endif
    </div>@endif
    <div class="studio-copy">
        <p class="eyebrow">{{ $brand['content']['studio_eyebrow'] }}</p>
        @if($salon->demo)
            <h2>{{ $brand['content']['studio_heading'] }}</h2>
            <p>{{ $brand['content']['studio_text'] }}</p>
        @else
            <h2>@if(!empty($salon->content['studio_heading'])){{ $brand['content']['studio_heading'] }}@else{{ 'Rólad szól.' }}<br>A részletekig.@endif</h2>
            <p class="preserve">{{ $brand['content']['studio_text'] }}</p>
        @endif
        <a class="text-link" href="{{ route('booking.form') }}">{{ $brand['content']['studio_booking'] }} <x-icon name="arrow-right" /></a>
    </div>
</section>
