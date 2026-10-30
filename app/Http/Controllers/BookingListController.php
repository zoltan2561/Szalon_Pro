<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Salon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingListController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:120',
            'date' => 'nullable|date_format:Y-m-d',
            'status' => ['nullable', Rule::in(['confirmed', 'completed', 'cancelled', 'no_show'])],
            'scope' => ['nullable', Rule::in(['upcoming', 'history', 'all'])],
        ]);
        $search = trim($filters['q'] ?? '');
        $phoneDigits = preg_replace('/\D+/', '', $search);
        $date = $filters['date'] ?? null;
        $status = $filters['status'] ?? null;
        $scope = $filters['scope'] ?? ($request->filled('q') || $date || $status ? 'all' : 'upcoming');

        $bookings = Booking::visibleTo($request->user())->with(['guest', 'staff'])
            ->when($search !== '', function ($query) use ($search, $phoneDigits): void {
                $query->whereHas('guest', function ($guest) use ($search, $phoneDigits): void {
                    $guest->where('name', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$search.'%');
                    if ($phoneDigits !== '') {
                        $guest->orWhere('phone_normalized', 'like', '%'.$phoneDigits.'%');
                    }
                });
            })
            ->when($date, function ($query) use ($date): void {
                $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, Salon::current()->timezone);
                $query->where('starts_at', '>=', $day->utc())->where('starts_at', '<', $day->addDay()->utc());
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($scope === 'upcoming', fn ($query) => $query->where('status', 'confirmed')->where('ends_at', '>', now()))
            ->when($scope === 'history', fn ($query) => $query->where(fn ($history) => $history->where('ends_at', '<=', now())->orWhere('status', '!=', 'confirmed')))
            ->when($scope === 'upcoming', fn ($query) => $query->orderBy('starts_at'), fn ($query) => $query->orderByDesc('starts_at'))
            ->orderByDesc('id')->paginate(30)->withQueryString();

        return view('admin.bookings', compact('bookings', 'search', 'date', 'status', 'scope'));
    }
}
