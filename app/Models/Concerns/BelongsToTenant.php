<?php

namespace App\Models\Concerns;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $query->where($query->getModel()->qualifyColumn('tenant_id'), app(TenantContext::class)->id());
        });
        static::saving(function (Model $model): void {
            $tenantId = app(TenantContext::class)->id();
            if (($model->exists && (int) $model->getOriginal('tenant_id') !== $tenantId)
                || ($model->tenant_id !== null && (int) $model->tenant_id !== $tenantId)) {
                throw ValidationException::withMessages(['tenant_id' => 'Ez az adat másik szalonhoz tartozik.']);
            }
            $model->setAttribute('tenant_id', $tenantId);
            foreach (self::tenantReferences() as $column => $table) {
                $value = $model->getAttribute($column);
                if ($value !== null && (! $model->exists || $model->isDirty($column))
                    && ! DB::table($table)->where('id', $value)->where('tenant_id', $tenantId)->exists()) {
                    throw ValidationException::withMessages([$column => 'A kiválasztott adat nem ehhez a szalonhoz tartozik.']);
                }
            }
        });
        static::deleting(function (Model $model): void {
            if ((int) $model->tenant_id !== app(TenantContext::class)->id()) {
                throw ValidationException::withMessages(['tenant_id' => 'Ez az adat másik szalonhoz tartozik.']);
            }
        });
    }

    /** @return array<string, string> */
    private static function tenantReferences(): array
    {
        return ['salon_id' => 'salons', 'staff_id' => 'staff', 'service_id' => 'services', 'guest_id' => 'guests',
            'booking_id' => 'bookings', 'booked_booking_id' => 'bookings', 'actor_id' => 'users', 'created_by_id' => 'users',
            'issued_by_user_id' => 'users', 'created_by_user_id' => 'users', 'reversed_by_user_id' => 'users',
            'notes_updated_by_id' => 'users', 'requested_by_id' => 'users', 'reviewed_by_id' => 'users',
            'google_calendar_connection_id' => 'google_calendar_connections', 'pass_plan_id' => 'pass_plans',
            'guest_pass_id' => 'guest_passes', 'gift_voucher_id' => 'gift_vouchers', 'leave_request_id' => 'leave_requests',
            'waitlist_entry_id' => 'waitlist_entries'];
    }
}
