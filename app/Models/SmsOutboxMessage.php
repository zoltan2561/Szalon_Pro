<?php

namespace App\Models;

use Database\Factories\SmsOutboxMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsOutboxMessage extends Model
{
    /** @use HasFactory<SmsOutboxMessageFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
