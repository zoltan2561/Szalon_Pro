@if($brand['show_gallery'] && $salon->gallery)
<section class="gallery-section wrap"><div class="section-heading"><div><p class="eyebrow">{{ $brand['content']['gallery_eyebrow'] }}</p><h2>{{ $brand['content']['gallery_heading'] }}</h2></div></div><div class="gallery">@foreach($salon->gallery as $picture)<img src="{{ Storage::url($picture) }}" alt="{{ $salon->name }} – {{ $salon->demo ? 'referencia' : 'galériakép' }} {{ $loop->iteration }}" loading="lazy" width="640" height="480">@endforeach</div></section>
@endif
