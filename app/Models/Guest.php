<?php

namespace App\Models;

use App\Services\SmsGateway;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        return ['notes' => 'encrypted', 'anonymized_at' => 'datetime', 'review_opt_in_at' => 'immutable_datetime', 'review_opt_out_at' => 'immutable_datetime', 'last_review_requested_at' => 'immutable_datetime'];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn (?string $value): ?string => auth()->user()?->isDemoViewer() ? 'Minta vendég #'.$this->id : $value);
    }

    protected function email(): Attribute
    {
        return Attribute::get(fn (?string $value): ?string => auth()->user()?->isDemoViewer() ? 'vendeg-'.$this->id.'@example.test' : $value);
    }

    protected function phone(): Attribute
    {
        return Attribute::get(fn (?string $value): ?string => auth()->user()?->isDemoViewer() ? '+36 00 000 0000' : $value);
    }

    public function scopeMatchingDemoIdentity(Builder $query, string $search): void
    {
        if (preg_match('/^(?:Minta vendég #|vendeg-)?([1-9][0-9]*)(?:@example\.test)?$/iu', trim($search), $matches)) {
            $query->whereKey((int) $matches[1]);
        } else {
            $query->whereRaw('1 = 0');
        }
    }
}
