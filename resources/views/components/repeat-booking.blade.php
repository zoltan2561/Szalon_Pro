<details class="repeat-booking" data-repeat-booking data-preview-url="{{ auth()->check() && request()->is('admin/*') ? route('admin.series.preview') : route('booking.series.preview') }}" @if((int) old('repeat_count', 1) > 1) open @endif>
    <summary>Rendszeresen szeretnék jönni</summary>
    <p class="muted">Előre lefoglalhatod a következő alkalmakat ugyanahhoz a szakemberhez. Minden dátumot ellenőrzünk.</p>
    <div class="form-grid">
        <label>Hány alkalom legyen összesen?<select name="repeat_count"><option value="1">Csak ez az egy alkalom</option>@for($count = 2; $count <= 6; $count++)<option value="{{ $count }}" @selected((int) old('repeat_count', 1) === $count)>{{ $count }} alkalom, a mostanival együtt</option>@endfor</select></label>
        <label>Milyen gyakran?<select name="repeat_weeks">@foreach([1,2,3,4,6,8] as $weeks)<option value="{{ $weeks }}" @selected((int) old('repeat_weeks', 4) === $weeks)>{{ $weeks === 1 ? 'Hetente' : $weeks.' hetente' }}</option>@endforeach</select></label>
    </div>
    <p class="muted" data-repeat-status role="status">Először válassz szabad időpontot.</p>
    <ol class="repeat-dates" data-repeat-dates></ol>
    <p class="muted" data-repeat-total></p>
    <div class="actions"><button type="button" class="button secondary" data-repeat-retry hidden>Alkalmak újraellenőrzése</button><button type="button" class="button secondary" data-repeat-done>Beállítva, tovább</button></div>
    <noscript><p>Mentéskor az összes alkalmat ellenőrizzük. Ha bármelyik foglalt, egyik új időpontot sem mentjük el.</p></noscript>
</details>
<p class="repeat-selection" data-repeat-summary aria-live="polite" hidden></p>
