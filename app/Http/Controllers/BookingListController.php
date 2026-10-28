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
        ]);
        $search = trim($filters['q'] ?? '');
        $phoneDigits = preg_replace('/\D+/', '', $search);
        $date = $filters['date'] ?? null;
        $status = $filters['status'] ?? null;

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
            ->orderByDesc('starts_at')->orderByDesc('id')->paginate(30)->withQueryString();

        return view('admin.bookings', compact('bookings', 'search', 'date', 'status'));
    }
}
