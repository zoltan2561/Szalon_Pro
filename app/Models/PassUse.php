<?php

namespace App\Models;

use Database\Factories\PassUseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassUse extends Model
{
    /** @use HasFactory<PassUseFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reversed_at' => 'datetime'];
    }

    public function guestPass(): BelongsTo
    {
        return $this->belongsTo(GuestPass::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
