@if($salon->featureEnabled('waitlist'))
<a href="{{ route('admin.waitlist.index') }}" @if(request()->routeIs('admin.waitlist.*')) aria-current="page" @endif><x-icon name="list" />Várólista</a>
@endif
@if(auth()->user()->role === 'owner')
@if($salon->featureEnabled('passes') || $salon->featureEnabled('loyalty'))
<a href="{{ route('admin.benefits.index') }}" @if(request()->routeIs('admin.benefits.*')) aria-current="page" @endif><x-icon name="sparkle" />Bérletek és hűségpontok</a>
@endif
@foreach(['services'=>['scissors','Szolgáltatások'],'staff'=>['user','Munkatársak'],'google.index'=>['calendar-blank','Google Naptár'],'notifications'=>['envelope-simple','Értesítések'],'modules.index'=>['squares-four','Modulok'],'settings'=>['gear-six','Beállítások']] as $key=>$item)
<a href="{{ route('admin.'.$key) }}" @if(request()->routeIs('admin.'.$key)) aria-current="page" @endif><x-icon :name="$item[0]" />{{ $item[1] }}</a>
@endforeach
@endif
