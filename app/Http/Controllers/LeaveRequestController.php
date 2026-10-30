<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\LeaveRequest;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\WorkException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = LeaveRequest::visibleTo($request->user())->with('staff', 'requester')->orderByRaw("case when status = 'pending' then 0 else 1 end")->orderByDesc('id')->paginate(20);
        $staff = Staff::where('active', true)->when($request->user()->role !== 'owner', fn ($query) => $query->whereKey($request->user()->staff_id))->orderBy('name')->get();
        $conflicts = [];
        foreach ($requests->where('status', 'pending') as $leave) {
            $conflicts[$leave->id] = $this->conflictingBookings($leave)->with('guest')->limit(6)->get();
        }

        return view('admin.leave-requests', compact('requests', 'staff', 'conflicts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $today = now(Salon::current()->timezone)->toDateString();
        $data = $request->validate([
            'staff_id' => $request->user()->role === 'owner' ? 'required|integer|exists:staff,id' : 'prohibited',
            'starts_on' => 'required|date_format:Y-m-d|after_or_equal:'.$today,
            'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on',
            'note' => 'nullable|string|max:255',
        ]);
        $staffId = $request->user()->role === 'owner' ? (int) $data['staff_id'] : (int) $request->user()->staff_id;
        abort_unless(Staff::whereKey($staffId)->where('active', true)->exists(), 422);
        $start = CarbonImmutable::parse($data['starts_on']);
        if ($start->diffInDays(CarbonImmutable::parse($data['ends_on'])) > 30 || $start->gt(now()->addYears(2))) {
            throw ValidationException::withMessages(['ends_on' => 'Egy kérés legfeljebb 31 napra, legfeljebb 2 évre előre szólhat.']);
        }
        DB::transaction(function () use ($staffId, $data, $request): void {
            Salon::whereKey(1)->increment('lock_version');
            if (LeaveRequest::where('staff_id', $staffId)->whereIn('status', ['pending', 'approved'])->whereDate('starts_on', '<=', $data['ends_on'])->whereDate('ends_on', '>=', $data['starts_on'])->exists()) {
                throw ValidationException::withMessages(['starts_on' => 'Erre az időszakra már van függő vagy jóváhagyott kérés.']);
            }
            LeaveRequest::create(['staff_id' => $staffId, 'requested_by_id' => $request->user()->id, 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'], 'note' => $data['note'] ?? null, 'status' => 'pending']);
        }, 5);

        return back()->with('success', 'A kérés elkészült. A napok a tulajdonos jóváhagyása után válnak szabaddá.');
    }

    public function review(LeaveRequest $leave, Request $request): RedirectResponse
    {
        abort_unless(LeaveRequest::visibleTo($request->user())->whereKey($leave->id)->exists(), 403);
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'reject', 'withdraw'])], 'review_note' => 'nullable|string|max:255']);
        abort_unless($request->user()->role === 'owner' || ($data['action'] === 'withdraw' && $leave->status === 'pending'), 403);
        DB::transaction(function () use ($leave, $request, $data): void {
            Salon::whereKey(1)->increment('lock_version');
            $current = LeaveRequest::lockForUpdate()->findOrFail($leave->id);
            if ($data['action'] === 'withdraw') {
                abort_unless($current->status === 'pending' || ($current->status === 'approved' && $request->user()->role === 'owner'), 422);
                WorkException::where('leave_request_id', $current->id)->delete();
                $current->update(['status' => 'withdrawn', 'reviewed_by_id' => $request->user()->id, 'reviewed_at' => now()]);

                return;
            }
            if ($current->status !== 'pending') {
                throw ValidationException::withMessages(['action' => 'Ezt a kérést már elbírálták. Frissítsd az oldalt.']);
            }
            if ($data['action'] === 'approve') {
                if ($this->conflictingBookings($current)->exists()) {
                    throw ValidationException::withMessages(['action' => 'Még vannak foglalások a kért napokon. Előbb helyezd át vagy rendezd őket, majd hagyd jóvá a kérést.']);
                }
                for ($day = $current->starts_on; $day->lte($current->ends_on); $day = $day->addDay()) {
                    $exception = WorkException::where('staff_id', $current->staff_id)->where('date', $day->toDateString())->first();
                    if ($exception && $exception->hours) {
                        throw ValidationException::withMessages(['action' => 'A kért napra már eltérő munkaidőt rögzítettél. Előbb rendezd a Munkatársak oldalon.']);
                    }
                    if (! $exception) {
                        WorkException::create(['staff_id' => $current->staff_id, 'date' => $day->toDateString(), 'hours' => [], 'leave_request_id' => $current->id]);
                    }
                }
            }
            $current->update(['status' => $data['action'] === 'approve' ? 'approved' : 'rejected', 'review_note' => $data['review_note'] ?? null, 'reviewed_by_id' => $request->user()->id, 'reviewed_at' => now()]);
        }, 5);

        return back()->with('success', ['approve' => 'A szabadnapokat jóváhagytad. Ezekre a napokra új foglalás már nem vehető fel.', 'reject' => 'A kérést elutasítottad.', 'withdraw' => 'A kérést visszavontuk.'][$data['action']]);
    }

    private function conflictingBookings(LeaveRequest $leave): Builder
    {
        $from = CarbonImmutable::parse($leave->starts_on->toDateString(), Salon::current()->timezone)->startOfDay()->utc();
        $until = CarbonImmutable::parse($leave->ends_on->toDateString(), Salon::current()->timezone)->addDay()->startOfDay()->utc();

        return Booking::where('staff_id', $leave->staff_id)->whereIn('status', ['confirmed', 'completed', 'no_show'])->where('busy_from', '<', $until)->where('busy_until', '>', $from);
    }
}
