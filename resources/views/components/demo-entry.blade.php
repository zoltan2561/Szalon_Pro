@if($demoAccessAvailable)
@if($demoViewer)
<a class="button{{ ($secondary ?? false) ? ' secondary' : '' }}" href="{{ route('admin.today') }}">Vissza az adminbemutatóhoz <x-icon name="arrow-right" /></a>
@else
<form method="post" action="{{ route('demo.login') }}" class="demo-entry">
    @csrf
    <button class="button{{ ($secondary ?? false) ? ' secondary' : '' }}">{{ $label ?? 'Adminbemutató megnyitása' }} <x-icon name="arrow-right" /></button>
    @if($showHint ?? true)<small>Jelszó nélkül · csak megtekintés · védett mintaadatok</small>@endif
</form>
@endif
@endif
