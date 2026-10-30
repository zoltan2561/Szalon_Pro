@extends('layouts.admin')
@section('title', 'Szabadnapok')
@section('content')
<div class="page-heading"><div><p class="eyebrow">TERVEZZÜNK ELŐRE</p><h1>Szabadnapok</h1><p class="muted">A kérés még nem módosítja a naptárat. A jóváhagyott napokra már nem lehet foglalni.</p></div></div>
<div class="booking-grid"><div>
    <section class="panel"><h2>Kérések és döntések</h2>
        @forelse($requests as $leave)
            <article class="leave-request-row">
                <div class="section-heading"><h3>{{ $leave->staff->name }}</h3><span class="badge {{ $leave->status === 'approved' ? 'completed' : '' }}">{{ ['pending'=>'Döntésre vár','approved'=>'Jóváhagyva','rejected'=>'Elutasítva','withdrawn'=>'Visszavonva'][$leave->status] }}</span></div>
                <p><strong>{{ $leave->starts_on->format('Y. m. d.') }} – {{ $leave->ends_on->format('Y. m. d.') }}</strong></p>
                @if($leave->note)<p>{{ $leave->note }}</p>@endif
                @if($leave->review_note)<p class="muted">Tulajdonosi válasz: {{ $leave->review_note }}</p>@endif
                @if(($conflicts[$leave->id] ?? collect())->isNotEmpty())<div class="notice"><strong>Előbb ezeket a foglalásokat kell rendezni:</strong><ul>@foreach($conflicts[$leave->id] as $visit)<li><a href="{{ route('admin.booking', $visit) }}">{{ $visit->starts_at->setTimezone($salon->timezone)->format('m. d. H:i') }} · {{ $visit->guest->name }}</a></li>@endforeach</ul></div>@endif
                @if(auth()->user()->role === 'owner' && $leave->status === 'pending')
                    <form method="post" action="{{ route('admin.leave.review', $leave) }}">@csrf
                        <label>Rövid válasz <span class="muted">(opcionális)</span><input name="review_note" maxlength="255"></label>
                        <div class="actions"><button class="button" name="action" value="approve" @disabled(($conflicts[$leave->id] ?? collect())->isNotEmpty())>Szabadnapok jóváhagyása</button><button class="button secondary" name="action" value="reject">Elutasítás</button></div>
                    </form>
                @endif
                @if($leave->status === 'pending' || (auth()->user()->role === 'owner' && $leave->status === 'approved'))<form method="post" action="{{ route('admin.leave.review', $leave) }}">@csrf<button class="text-button" name="action" value="withdraw">{{ $leave->status === 'approved' ? 'Jóváhagyás visszavonása' : 'Kérés visszavonása' }}</button></form>@endif
            </article>
        @empty<p class="muted">Még nincs szabadnapkérés. Új kérést az űrlapon adhatsz meg.</p>@endforelse
        {{ $requests->links() }}
    </section>
</div><aside class="panel"><h2>Szabadnapot kérek</h2><form method="post" action="{{ route('admin.leave.store') }}">@csrf
    @if(auth()->user()->role === 'owner')<label>Munkatárs<select name="staff_id" required>@foreach($staff as $person)<option value="{{ $person->id }}" @selected(old('staff_id') == $person->id)>{{ $person->name }}</option>@endforeach</select></label>@else<p>{{ $staff->first()?->name }}</p>@endif
    <div class="form-grid"><label>Első nap<input type="date" name="starts_on" value="{{ old('starts_on') }}" min="{{ now($salon->timezone)->toDateString() }}" required></label><label>Utolsó nap<input type="date" name="ends_on" value="{{ old('ends_on') }}" min="{{ now($salon->timezone)->toDateString() }}" required></label></div>
    <label>Megjegyzés <span class="muted">(opcionális)</span><textarea name="note" rows="2" maxlength="255">{{ old('note') }}</textarea></label>
    <p class="muted">Egy kérés legfeljebb 31 napra szólhat. A tulajdonos ellenőrzi a már meglévő foglalásokat.</p><button class="button full">Kérés elküldése</button>
</form></aside></div>
@endsection
