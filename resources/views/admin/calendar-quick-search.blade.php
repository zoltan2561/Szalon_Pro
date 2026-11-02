@if($canManageBookings && $calendarStaff->where('active', true)->isNotEmpty())
<details class="calendar-find-slots">
    <summary>Szabad időpontot keresek munkatárs szerint</summary>
    <p><small>A foglalható időket a szolgáltatás és a munkarend alapján ellenőrizzük.</small></p>
    <div>@foreach($calendarStaff->where('active', true) as $person)<a href="{{ route('admin.create', ['date' => $date, 'staff_id' => $person->id]) }}"><strong>{{ $person->name }}</strong><span>Szabad időpontok keresése ↗</span></a>@endforeach</div>
</details>
@endif
