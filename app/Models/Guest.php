<?php

namespace App\Models;

use App\Services\SmsGateway;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['notes'];

    protected static function booted(): void
    {
        static::saving(function (self $guest): void {
            $guest->phone_normalized = app(SmsGateway::class)->normalizeNumber($guest->phone);
        });
    }

    protected function casts(): array
    {
        return ['notes' => 'encrypted', 'anonymized_at' => 'datetime'];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
