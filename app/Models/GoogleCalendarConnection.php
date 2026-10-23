<?php

namespace App\Models;

use Database\Factories\GoogleCalendarConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleCalendarConnection extends Model
{
    /** @use HasFactory<GoogleCalendarConnectionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'token_expires_at' => 'immutable_datetime', 'last_imported_at' => 'immutable_datetime', 'last_exported_at' => 'immutable_datetime'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(GoogleCalendarEvent::class);
    }

    public function busyEvents(): HasMany
    {
        return $this->hasMany(GoogleCalendarBusy::class);
    }
}
