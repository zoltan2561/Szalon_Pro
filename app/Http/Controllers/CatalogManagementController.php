<?php

namespace App\Http\Controllers;

use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogManagementController extends Controller
{
    public function moveService(Request $request, Service $service): RedirectResponse
    {
        return $this->move($request, $service);
    }

    public function moveStaff(Request $request, Staff $staff): RedirectResponse
    {
        return $this->move($request, $staff);
    }

    public function archiveService(Request $request, Service $service): RedirectResponse
    {
        return $this->archive($request, $service);
    }

    public function archiveStaff(Request $request, Staff $staff): RedirectResponse
    {
        return $this->archive($request, $staff);
    }

    public function deleteService(Request $request, Service $service): RedirectResponse
    {
        $request->validate(['confirm' => 'accepted']);

        return $this->delete($service, ['bookings', 'service_staff', 'waitlist_entries', 'pass_plans', 'guest_passes', 'gift_vouchers']);
    }

    public function deleteStaff(Request $request, Staff $staff): RedirectResponse
    {
        $request->validate(['confirm' => 'accepted']);

        return $this->delete($staff, ['bookings', 'service_staff', 'users', 'time_blocks', 'work_exceptions', 'google_calendar_connections', 'waitlist_entries']);
    }

    private function move(Request $request, Model $item): RedirectResponse
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        DB::transaction(function () use ($item, $data): void {
            Salon::whereKey(1)->increment('lock_version');
            $items = $item->newQuery()->orderBy('sort_order')->orderBy('id')->get();
            $index = $items->search(fn (Model $row): bool => $row->is($item));
            if ($index === false) {
                abort(404);
            }
            $other = $index + ($data['direction'] === 'up' ? -1 : 1);
            if ($other < 0 || $other >= $items->count()) {
                return;
            }
            $rows = $items->all();
            [$rows[$index], $rows[$other]] = [$rows[$other], $rows[$index]];
            foreach ($rows as $position => $row) {
                $row->update(['sort_order' => $position + 1]);
            }
        }, 5);

        return back()->with('success', 'A sorrendet elmentettük.');
    }

    private function archive(Request $request, Model $item): RedirectResponse
    {
        $data = $request->validate(['active' => 'required|boolean']);
        DB::transaction(function () use ($item, $data): void {
            Salon::whereKey(1)->increment('lock_version');
            $item->newQuery()->findOrFail($item->getKey())->update(['active' => (bool) $data['active']]);
        }, 5);

        return back()->with('success', $data['active'] ? 'Újra aktív.' : 'Archiváltuk. A korábbi foglalások megmaradtak.');
    }

    /** @param list<string> $tables */
    private function delete(Model $item, array $tables): RedirectResponse
    {
        DB::transaction(function () use ($item, $tables): void {
            Salon::whereKey(1)->increment('lock_version');
            $column = $item instanceof Staff ? 'staff_id' : 'service_id';
            foreach ($tables as $table) {
                if (DB::table($table)->where($column, $item->getKey())->exists()) {
                    throw ValidationException::withMessages(['catalog' => 'A törlés nem lehetséges, mert kapcsolódó adat van. Archiváld, hogy az előzmények megmaradjanak.']);
                }
            }
            $item->newQuery()->findOrFail($item->getKey())->delete();
        }, 5);

        return back()->with('success', 'A bejegyzést töröltük.');
    }
}
