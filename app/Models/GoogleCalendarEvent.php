<?php

namespace App\Models;

use Database\Factories\GoogleCalendarEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCalendarEvent extends Model
{
    /** @use HasFactory<GoogleCalendarEventFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['synced_revision' => 'integer'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleCalendarConnection::class, 'google_calendar_connection_id');
    }
}
