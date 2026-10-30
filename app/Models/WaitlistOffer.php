<?php

namespace App\Models;

use Database\Factories\WaitlistOfferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistOffer extends Model
{
    /** @use HasFactory<WaitlistOfferFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['manage_token', 'token_hash'];

    protected function casts(): array
    {
        return ['manage_token' => 'encrypted', 'starts_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'scheduled_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(WaitlistEntry::class, 'waitlist_entry_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
