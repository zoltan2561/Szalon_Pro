<?php

namespace App\Services;

use App\Models\GoogleCalendarConnection;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;

class SalonSetup
{
    /** @return list<array{label: string, url: string, complete: bool}> */
    public function steps(Salon $salon): array
    {
        return [
            ['label' => 'Szolgáltatások és árak', 'url' => route('admin.services'), 'complete' => Service::where('active', true)->exists()],
            ['label' => 'Cím és elérhetőség', 'url' => route('admin.settings').'#settings-business', 'complete' => filled($salon->address) && filled($salon->email) && filled($salon->phone)],
            ['label' => 'Nyitvatartás', 'url' => route('admin.settings').'#settings-hours', 'complete' => collect($salon->opening_hours ?? [])->contains(fn (array $ranges): bool => $ranges !== [])],
            ['label' => 'Munkatársak és munkarend', 'url' => route('admin.staff'), 'complete' => Staff::where('active', true)->get()->contains(fn (Staff $staff): bool => collect($staff->weekly_hours ?? [])->contains(fn (array $ranges): bool => $ranges !== []))],
            ['label' => 'Google Naptár összekötése', 'url' => route('admin.google.index'), 'complete' => GoogleCalendarConnection::where('sync_status', 'connected')->whereHas('staff', fn ($query) => $query->where('active', true))->exists()],
        ];
    }
}
