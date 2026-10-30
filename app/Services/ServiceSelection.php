<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceSelection
{
    /** @param list<int|string> $extras */
    public function combine(Service $primary, array $extras = []): Service
    {
        $ids = array_values(array_unique(array_map('intval', [$primary->id, ...$extras])));
        if (count($ids) > 5) {
            throw ValidationException::withMessages(['extra_service_ids' => 'Egy alkalomhoz legfeljebb 5 szolgáltatást választhatsz.']);
        }
        if (count($ids) === 1) {
            return $primary;
        }
        $services = Service::with('staff')->whereIn('id', $ids)->where('active', true)->get()->sortBy(fn (Service $service): int => array_search($service->id, $ids, true))->values();
        if ($services->count() !== count($ids)) {
            throw ValidationException::withMessages(['extra_service_ids' => 'Az egyik szolgáltatás már nem foglalható. Válassz újra.']);
        }
        $eligibleIds = $services->first()->staff->where('active', true)->modelKeys();
        foreach ($services->skip(1) as $service) {
            $eligibleIds = array_values(array_intersect($eligibleIds, $service->staff->where('active', true)->modelKeys()));
        }
        $combined = clone $primary;
        $combined->name = Str::limit($services->pluck('name')->join(' + '), 250);
        $combined->duration_minutes = $services->sum('duration_minutes') + $services->sum('buffer_before') + $services->sum('buffer_after') - $services->first()->buffer_before - $services->last()->buffer_after;
        $combined->buffer_before = $services->first()->buffer_before;
        $combined->buffer_after = $services->last()->buffer_after;
        $combined->price = $services->sum('price');
        $combined->price_kind = $services->contains('price_kind', 'indicative') ? 'indicative' : ($services->contains('price_kind', 'from') ? 'from' : 'fixed');
        if ($combined->duration_minutes > 720 || $combined->price > 10000000) {
            throw ValidationException::withMessages(['extra_service_ids' => 'Ez a szolgáltatáskombináció túl hosszú vagy túl nagy értékű. Kérj külön alkalmakat.']);
        }
        $combined->setRelation('staff', $primary->staff->whereIn('id', $eligibleIds)->values());
        $combined->setRelation('selectedServices', $services);

        return $combined;
    }

    /** @return list<array{id: int, name: string, duration: int, price: int, price_kind: string, buffer_before: int, buffer_after: int}> */
    public function items(Service $service): array
    {
        $items = $service->relationLoaded('selectedServices') ? $service->getRelation('selectedServices') : collect([$service]);

        return $items->map(fn (Service $item): array => ['id' => $item->id, 'name' => $item->name, 'duration' => $item->duration_minutes, 'price' => $item->price, 'price_kind' => $item->price_kind, 'buffer_before' => $item->buffer_before, 'buffer_after' => $item->buffer_after])->all();
    }

    public function eligible(Service $service, Staff $staff): bool
    {
        return ! $service->relationLoaded('selectedServices') || $service->staff->contains('id', $staff->id);
    }

    public function forBooking(Booking $booking): Service
    {
        $service = clone $booking->service;
        if (count($booking->service_items ?? []) > 1) {
            $ids = array_column($booking->service_items, 'id');
            $services = Service::with('staff')->whereIn('id', $ids)->get();
            $eligibleIds = $service->staff->where('active', true)->modelKeys();
            foreach ($services as $current) {
                $eligibleIds = array_values(array_intersect($eligibleIds, $current->staff->where('active', true)->modelKeys()));
            }
            $service->active = $services->count() === count($ids) && $services->every(fn (Service $item): bool => $item->active);
            $service->setRelation('staff', $service->staff->whereIn('id', $eligibleIds)->values());
            $service->setRelation('selectedServices', $services);
        }
        $service->duration_minutes = $booking->duration_minutes;
        $service->buffer_before = $booking->buffer_before;
        $service->buffer_after = $booking->buffer_after;

        return $service;
    }
}
