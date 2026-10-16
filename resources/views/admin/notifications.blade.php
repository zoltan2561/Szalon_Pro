@extends('layouts.admin')
@section('title','Értesítések')
@section('content')<div class="page-heading"><div><p class="eyebrow">VISSZAIGAZOLÁSOK ÉS EMLÉKEZTETŐK</p><h1>Értesítések</h1><p class="muted">A foglalások akkor is érvényesek maradnak, ha egy e-mail küldése sikertelen.</p></div></div>@if(config('mail.default')==='array')<div class="notice">Helyi levélpróba aktív. A rendszer nem küld valódi e-mailt.</div>
@endif
<div class="panel">@forelse($messages as $message)<div class="history-row"><div><a href="{{ route('admin.booking',$message->booking_id) }}">Foglalás #{{ $message->booking_id }}</a><p>{{ ['confirmation'=>'Visszaigazolás','changed'=>'Módosítás','cancelled'=>'Lemondás','reminder'=>'Emlékeztető'][$message->kind] }} · {{ $message->scheduled_at->setTimezone($salon->timezone)->format('Y. m. d. H:i') }}</p><small>{{ $message->attempts }} próbálkozás @if($message->error_code)· {{ $message->error_code }}
@endif
</small></div><span class="badge">{{ ['pending'=>'Sorban áll','sending'=>'Küldés folyamatban','retry'=>'Újrapróbálkozásra vár','sent'=>config('mail.default')==='array' ? 'Helyi próba feldolgozva':'Küldő átvette','failed'=>'Sikertelen','obsolete'=>'Elavult, nem küldhető'][$message->status] }}</span>@if(in_array($message->status,['retry','failed']))<form method="post" action="{{ route('admin.retry',$message) }}">@csrf<button class="button secondary small">Újrapróbálkozás</button></form>
@endif
</div>
@empty
<div class="empty">Még nincs kiküldésre váró értesítés.</div>
@endforelse
</div>{{ $messages->links() }}
@endsection
