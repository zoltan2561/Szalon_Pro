@if($brand['show_gallery'] && $salon->gallery)
<section class="gallery-section wrap"><div class="section-heading"><div><p class="eyebrow">EGY PILLANTÁS HOZZÁNK</p><h2>A szalon és a munkáink</h2></div></div><div class="gallery">@foreach($salon->gallery as $picture)<img src="{{ Storage::url($picture) }}" alt="{{ $salon->name }} – referencia {{ $loop->iteration }}" loading="lazy" width="640" height="480">@endforeach</div></section>
@endif
