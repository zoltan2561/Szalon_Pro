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

    protected $hidden = ['manage_token', 'token_hash', 'request_hash', 'request_key', 'work_notes', 'work_photos'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'busy_from' => 'immutable_datetime', 'busy_until' => 'immutable_datetime', 'token_expires_at' => 'immutable_datetime', 'manage_token' => 'encrypted', 'sms_opt_in' => 'boolean', 'service_items' => 'array', 'work_notes' => 'array', 'work_photos' => 'array', 'notes_updated_at' => 'immutable_datetime', 'arrived_at' => 'immutable_datetime', 'started_at' => 'immutable_datetime'];
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

    public function isAutomaticallyCompleted(): bool
    {
        if ($this->status !== 'completed') {
            return false;
        }

        $event = $this->relationLoaded('events') ? $this->events->where('action', 'complete')->sortByDesc('id')->first() : $this->events()->where('action', 'complete')->latest('id')->first();

        return $event?->action === 'complete' && ($event->getAttribute('changes')['automatic'] ?? false) === true;
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

    public function visitLabel(): string
    {
        if ($this->status === 'confirmed') {
            return $this->started_at ? 'Folyamatban' : ($this->arrived_at ? 'Megérkezett' : $this->label());
        }

        return $this->label();
    }

    /** @return array{guest_id: int, service_id: int, staff_id: int, extra_service_ids: list<int>} */
    public function rebookingParameters(): array
    {
        $extraIds = collect($this->service_items ?? [])->pluck('id')->reject(fn ($id): bool => (int) $id === $this->service_id)->map(fn ($id): int => (int) $id)->all();

        return ['guest_id' => $this->guest_id, 'service_id' => $this->service_id, 'staff_id' => $this->staff_id, 'extra_service_ids' => Service::where('active', true)->whereIn('id', $extraIds)->pluck('id')->all()];
    }
}
