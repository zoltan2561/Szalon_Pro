<?php

namespace App\Models;

use Database\Factories\GuestPassFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestPass extends Model
{
    /** @use HasFactory<GuestPassFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['visits_total' => 'integer', 'visits_remaining' => 'integer', 'expires_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PassPlan::class, 'pass_plan_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function uses(): HasMany
    {
        return $this->hasMany(PassUse::class);
    }

    public function isUsable(): bool
    {
        return $this->visits_remaining > 0 && $this->voided_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
