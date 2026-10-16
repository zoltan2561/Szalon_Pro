@if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>
@endif

@if($errors->any())<div class="notice error" role="alert"><strong>Ellenőrizd a megadott adatokat.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>
@endforeach
</ul></div>
@endif
