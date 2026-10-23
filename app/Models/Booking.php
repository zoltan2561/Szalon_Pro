<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['manage_token', 'token_hash', 'request_hash', 'request_key'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'busy_from' => 'immutable_datetime', 'busy_until' => 'immutable_datetime', 'token_expires_at' => 'immutable_datetime', 'manage_token' => 'encrypted', 'sms_opt_in' => 'boolean'];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role !== 'owner') {
            $query->where('staff_id', $user->staff_id ?? 0);
        }
    }

    public function label(): string
    {
        return ['confirmed' => 'Visszaigazolt', 'completed' => 'Elvégezve', 'cancelled' => 'Lemondva', 'no_show' => 'Nem jelent meg'][$this->status];
    }
}
