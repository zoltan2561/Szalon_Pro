<?php

namespace App\Models;

use Database\Factories\PassPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PassPlan extends Model
{
    /** @use HasFactory<PassPlanFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'visits_total' => 'integer', 'validity_days' => 'integer'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function guestPasses(): HasMany
    {
        return $this->hasMany(GuestPass::class);
    }
}
