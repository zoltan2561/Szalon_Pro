<?php

namespace App\Http\Controllers;

use App\Models\Salon;
use App\Services\SalonStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StatisticsController extends Controller
{
    public function index(Request $request, SalonStatistics $statistics): View
    {
        abort_unless(Salon::current()->featureEnabled('statistics'), 404);
        $data = $request->validate(['period' => ['nullable', Rule::in(['week', 'month'])], 'date' => 'nullable|date_format:Y-m-d']);
        $period = $data['period'] ?? 'month';
        $day = CarbonImmutable::parse($data['date'] ?? now(Salon::current()->timezone)->toDateString(), Salon::current()->timezone);
        $from = $period === 'week' ? $day->startOfWeek() : $day->startOfMonth();
        $until = $period === 'week' ? $from->addWeek() : $from->addMonth();

        return view('admin.statistics', ['statistics' => $statistics->summarize($from, $until), 'from' => $from, 'until' => $until, 'period' => $period]);
    }
}
