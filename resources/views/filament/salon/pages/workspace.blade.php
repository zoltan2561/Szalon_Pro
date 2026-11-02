<x-filament-panels::page>
    @unless(\App\Models\Salon::current()->demo)
        @include('components.salon-setup', ['filament' => true])
    @endunless
    <x-filament::section heading="A szalon napi munkája">
        <p>A foglalásokat és a vendégeket a megszokott felületen kezeled. A beállítások csak a saját szalonodra érvényesek.</p>
        <div style="display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1.5rem">
            <x-filament::button :href="route('admin.today')" tag="a">Mai nap</x-filament::button>
            <x-filament::button :href="route('admin.bookings.index')" tag="a" color="gray">Foglalások</x-filament::button>
            <x-filament::button :href="route('admin.guests')" tag="a" color="gray">Vendégek</x-filament::button>
            <x-filament::button :href="route('admin.services')" tag="a" color="gray">Szolgáltatások és árak</x-filament::button>
            <x-filament::button :href="route('admin.staff')" tag="a" color="gray">Munkatársak</x-filament::button>
        </div>
    </x-filament::section>
    <x-filament::section heading="Arculat és tartalom">
        <p>Logó, képek, színek, szövegek, nyitvatartás és elérhetőség egy helyen.</p>
        <div style="display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1.5rem">
            <x-filament::button :href="route('admin.settings')" tag="a">Saját oldal beállításai</x-filament::button>
            <x-filament::button :href="route('home')" tag="a" color="gray" target="_blank">Vendégoldal megnyitása</x-filament::button>
        </div>
    </x-filament::section>
    @if (session()->has('impersonator_id'))
        <x-filament::section heading="Főadmin belépés">
            <p>Jelenleg a szalon tulajdonosaként dolgozol. A módosítások a szalon saját adatait érintik.</p>
            <form method="post" action="{{ route('tenancy.impersonate.stop') }}" class="mt-6">
                @csrf
                <x-filament::button type="submit" color="gray">Vissza a főadminba</x-filament::button>
            </form>
        </x-filament::section>
    @endif
</x-filament-panels::page>
