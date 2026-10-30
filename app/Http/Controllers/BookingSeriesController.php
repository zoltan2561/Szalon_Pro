<?php

namespace App\Http\Controllers;

use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Services\Availability;
use App\Services\BookingSeries;
use App\Services\ServiceSelection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingSeriesController extends Controller
{
    public function preview(Request $request, BookingSeries $series, Availability $availability, ServiceSelection $selection): JsonResponse
    {
        $data = $request->validate(['service_id' => 'required|integer|exists:services,id', 'extra_service_ids' => 'nullable|array|max:4', 'extra_service_ids.*' => 'integer|distinct|exists:services,id', 'slot' => 'required|string', 'repeat_count' => 'required|integer|between:1,6', 'repeat_weeks' => ['required', Rule::in([1, 2, 3, 4, 6, 8])]]);
        $parts = explode('|', $data['slot'], 2);
        abort_unless(count($parts) === 2 && ctype_digit($parts[0]), 422);
        if ($request->routeIs('admin.*')) {
            abort_unless($request->user()->role === 'owner' || ($request->user()->can_manage_bookings && (int) $request->user()->staff_id === (int) $parts[0]), 403);
        } else {
            abort_unless(Salon::current()->published && $availability->ready(), 404);
        }
        $service = $selection->combine(Service::findOrFail($data['service_id']), $data['extra_service_ids'] ?? []);
        $dates = $series->preview($service, Staff::findOrFail($parts[0]), $availability->parse($parts[1]), (int) $data['repeat_weeks'], (int) $data['repeat_count']);

        return response()->json(['dates' => $dates, 'available' => collect($dates)->every('available'), 'price_per_visit' => $service->price, 'price_kind' => $service->price_kind, 'total' => $service->price * (int) $data['repeat_count']])->header('Cache-Control', 'private, no-store');
    }
}
