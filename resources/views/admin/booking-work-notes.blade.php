@if((auth()->user()->role === 'owner' || auth()->user()->can_view_notes) && !$booking->guest->anonymized_at)
<details id="work-notes" class="panel work-notes" @if($errors->hasAny(['work_notes', 'work_notes.*', 'photos', 'photos.*'])) open @endif>
    <summary><span><strong>Szakmai munkalap</strong><small>{{ count($booking->work_photos ?? []) ? count($booking->work_photos).' referenciafotó · ' : '' }}{{ \Illuminate\Support\Str::limit($booking->work_notes['formula'] ?? $booking->work_notes['style'] ?? $booking->work_notes['note'] ?? 'Szín, termék, fazon és megjegyzés ehhez a látogatáshoz', 110) }}</small></span></summary>
    <p class="muted">Jegyezd fel, mivel dolgoztál, hogy legközelebb könnyű legyen folytatni. A vendég ezt nem látja.</p>
    <form method="post" enctype="multipart/form-data" action="{{ route('admin.booking.notes', $booking) }}">@csrf
        <input type="hidden" name="notes_revision" value="{{ $booking->notes_revision }}">
        <div class="form-grid">
            <label>Szín vagy recept<input name="work_notes[formula]" maxlength="1500" value="{{ old('work_notes.formula', $booking->work_notes['formula'] ?? '') }}" placeholder="Például festékárnyalat vagy körömszín"></label>
            <label>Felhasznált termékek<input name="work_notes[products]" maxlength="1500" value="{{ old('work_notes.products', $booking->work_notes['products'] ?? '') }}"></label>
        </div>
        <label>Fazon, forma, technika<input name="work_notes[style]" maxlength="1500" value="{{ old('work_notes.style', $booking->work_notes['style'] ?? '') }}"></label>
        <label>Fontos a következő alkalomhoz<textarea name="work_notes[note]" rows="3" maxlength="1500">{{ old('work_notes.note', $booking->work_notes['note'] ?? '') }}</textarea></label>
        @if($booking->work_photos)<div class="visit-photos">@foreach($booking->work_photos as $photo)<figure><img src="{{ route('admin.booking.photo', [$booking, $loop->index]) }}" alt="Szakmai referenciafotó {{ $loop->iteration }}" loading="lazy"><label class="check"><input type="checkbox" name="remove_photos[]" value="{{ $loop->index }}"><span>Fotó törlése mentéskor</span></label></figure>@endforeach</div>@endif
        <details><summary>Referenciafotó hozzáadása</summary><label>Legfeljebb 4 fotó, képenként 4 MB<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label><p class="muted">Csak a vendég által engedélyezett szakmai fotót tölts fel. Egészségügyi vagy más érzékeny adatot ne írj a munkalapra.</p></details>
        <button class="button secondary">Munkalap mentése</button>
        @if($booking->notes_updated_at)<p class="muted">Utoljára mentve: {{ $booking->notes_updated_at->setTimezone($salon->timezone)->format('Y. m. d. H:i') }}</p>@endif
    </form>
</details>
@endif
