@if($booking->series_key)
<label>Melyik alkalmat módosítod?<select name="repeat_scope"><option value="single">Csak ezt az egy alkalmat</option><option value="following">Ezt és a sorozat későbbi időpontjait is</option></select></label>
<p class="muted">A sorozat áthelyezése minden későbbi időpontot ugyanannyival tol el. Ütközés esetén az eredeti időpontok megmaradnak.</p>
@endif
