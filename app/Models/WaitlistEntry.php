<?php

namespace App\Models;

use Database\Factories\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    /** @use HasFactory<WaitlistEntryFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['preferred_date' => 'immutable_date'];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booked_booking_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role !== 'owner') {
            $query->where('staff_id', $user->staff_id ?? 0);
        }
    }
}
