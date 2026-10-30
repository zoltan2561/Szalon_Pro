<?php

namespace App\Services;

use App\Models\Salon;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class DemoAccess
{
    public function available(): bool
    {
        return $this->viewer() !== null;
    }

    public function viewer(): ?User
    {
        if (! config('salon.demo_viewer_enabled') || ! Schema::hasColumn('users', 'is_demo_viewer')) {
            return null;
        }
        $salon = Salon::find(1);
        if (! $salon?->published || ! $salon->demo) {
            return null;
        }

        return User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) config('salon.demo_viewer_email')))])->where('is_demo_viewer', true)->where('role', 'owner')->first();
    }

    public function allowsRoute(string $routeName): bool
    {
        return in_array($routeName, [
            'home', 'privacy', 'booking.form',
            'admin.today', 'admin.calendar', 'admin.bookings.index', 'admin.booking',
            'admin.guests', 'admin.guest', 'admin.create', 'admin.services', 'admin.staff',
            'admin.statistics.index', 'admin.series.preview',
        ], true);
    }
}
