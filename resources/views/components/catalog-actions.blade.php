@if($item->exists)
<div class="actions" aria-label="Sorrend és állapot">
    <form method="post" action="{{ route('admin.'.$type.'.move', $item) }}">@csrf<button class="button secondary small" name="direction" value="up" aria-label="{{ $item->name }} feljebb">↑ Feljebb</button></form>
    <form method="post" action="{{ route('admin.'.$type.'.move', $item) }}">@csrf<button class="button secondary small" name="direction" value="down" aria-label="{{ $item->name }} lejjebb">↓ Lejjebb</button></form>
    <form method="post" action="{{ route('admin.'.$type.'.archive', $item) }}">@csrf<input type="hidden" name="active" value="{{ $item->active ? 0 : 1 }}"><button class="button secondary small">{{ $item->active ? 'Archiválás' : 'Újra aktiválás' }}</button></form>
    <form method="post" action="{{ route('admin.'.$type.'.delete', $item) }}">@csrf @method('DELETE')<label class="check"><input type="checkbox" name="confirm" value="1" required><span>Véglegesen törlöm</span></label><button class="button danger small">Törlés</button></form>
</div>
<p class="muted">A törlés csak kapcsolódó adatok nélkül lehetséges; az archiválás megőrzi az előzményeket.</p>
@endif
