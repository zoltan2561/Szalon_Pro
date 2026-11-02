@if($demoAccessAvailable)
@if($demoViewer)
<a class="button{{ ($secondary ?? false) ? ' secondary' : '' }}" href="{{ route($destination ?? 'admin.today') }}">{{ $label ?? 'Admin bemutató ('.$demoStyleLabel.')' }} <x-icon name="arrow-right" /></a>
@else
<form method="post" action="{{ route('demo.login') }}" class="demo-entry">
    @csrf
    <input type="hidden" name="destination" value="{{ $destination ?? 'admin.today' }}">
    <button class="button{{ ($secondary ?? false) ? ' secondary' : '' }}">{{ $label ?? 'Admin bemutató ('.$demoStyleLabel.')' }} <x-icon name="arrow-right" /></button>
    @if($showHint ?? true)<small>Jelszó nélkül · csak megtekintés · védett mintaadatok</small>@endif
</form>
@endif
@endif
