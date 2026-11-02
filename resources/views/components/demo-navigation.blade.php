<aside class="demo-navigation" data-demo-navigation aria-label="SzalonPRO bemutató">
    <div class="demo-navigation-inner">
        <a class="demo-navigation-home" href="{{ $demoMarketingUrl }}"><strong>SzalonPRO</strong>@if($demoViewer)<span>← Vissza a SzalonPRO főoldalra</span>@endif</a>
        <div class="demo-navigation-industries">
            <p class="demo-navigation-current">Most ezt nézed: {{ $demoStyleLabel }} bemutató</p>
            @if(count($demoGuestUrls) === 3)
            <nav class="demo-style-nav" aria-label="Szakági demó kiválasztása">
                @foreach(['salon' => 'Fodrászat', 'barber' => 'Barber', 'nails' => 'Körmös'] as $style => $label)
                    <a href="{{ $demoGuestUrls[$style] }}" @if($brand['theme'] === $style) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            @endif
        </div>
        <div class="demo-navigation-actions">
            @if($demoAccessAvailable)
                @include('components.demo-entry', ['label' => 'Admin bemutató ('.$demoStyleLabel.')', 'showHint' => false, 'secondary' => true])
            @else
                <a class="demo-navigation-action" href="{{ route('admin.today') }}">Admin bemutató ({{ $demoStyleLabel }})</a>
            @endif
            @if($demoSalesContactUrl)<a class="demo-navigation-action demo-navigation-contact" href="{{ $demoSalesContactUrl }}">Saját szalont szeretnék</a>@endif
        </div>
    </div>
</aside>
