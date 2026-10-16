<section aria-labelledby="preset-title">
    <p class="eyebrow">HÁROM MENTETT KIINDULÓPONT</p><h2 id="preset-title">Válassz hangulatot.</h2>
    <p class="settings-note">Egy kattintással alkalmazhatod a szakági színeket, betűket és elrendezést. A saját szövegeid és feltöltött képeid megmaradnak. Ezután minden részlet tovább alakítható.</p>
    <div class="preset-grid">@foreach(config('branding.presets') as $key => $preset)
        <form class="preset-card {{ $salon->theme === $key ? 'selected' : '' }}" method="post" action="{{ route('admin.branding.preset') }}">@csrf<input type="hidden" name="preset" value="{{ $key }}">
            <img src="{{ asset('assets/branding/'.$preset['image'].'-hero-mobile.webp') }}" alt="{{ $preset['label'] }} arculat hangulatképe" width="800" height="500" loading="lazy">
            <div class="preset-copy"><h2>{{ $preset['label'] }}</h2><p>{{ $preset['description'] }}</p>@if($salon->theme === $key)<span class="badge completed">Jelenlegi profil</span>@endif<button class="button {{ $salon->theme !== $key ? 'secondary' : '' }}" type="submit" aria-label="{{ $preset['label'] }} arculat alkalmazása">{{ $salon->theme === $key ? 'Alapbeállítások visszaállítása' : 'Arculat alkalmazása' }} <x-icon name="check" /></button></div>
        </form>
    @endforeach</div>
</section>
