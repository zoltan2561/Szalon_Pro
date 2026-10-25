<?php

namespace App\Http\Controllers;

use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Services\BookingLinks;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class SharingController extends Controller
{
    public function index(Request $request, BookingLinks $links): View
    {
        $filters = $this->filters($request);
        $urls = [];
        foreach (BookingLinks::Sources as $source) {
            $urls[$source] = $links->url($source, $filters['service_id'] ?? null, $filters['staff_id'] ?? null);
        }

        return view('admin.sharing', ['urls' => $urls, 'filters' => $filters, 'services' => Service::where('active', true)->get(), 'staff' => Staff::where('active', true)->get()]);
    }

    public function qr(Request $request, BookingLinks $links): Response
    {
        $filters = $this->filters($request);
        $source = $request->validate(['utm_source' => ['required', Rule::in(BookingLinks::Sources)]])['utm_source'];

        return response($links->qr($links->url($source, $filters['service_id'] ?? null, $filters['staff_id'] ?? null)), 200, ['Content-Type' => 'image/svg+xml', 'Content-Disposition' => 'attachment; filename="foglalasi-qr.svg"', 'Cache-Control' => 'private, no-store']);
    }

    /** @return array{service_id?: int|null, staff_id?: int|null} */
    private function filters(Request $request): array
    {
        abort_unless(Salon::current()->featureEnabled('sharing'), 404);
        $data = $request->validate(['service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('active', true)], 'staff_id' => ['nullable', 'integer', Rule::exists('staff', 'id')->where('active', true)]]);
        if (! empty($data['service_id']) && ! empty($data['staff_id'])) {
            abort_unless(Service::findOrFail($data['service_id'])->staff()->whereKey($data['staff_id'])->exists(), 422);
        }

        return array_map(static fn ($value) => $value === null ? null : (int) $value, $data);
    }
}
