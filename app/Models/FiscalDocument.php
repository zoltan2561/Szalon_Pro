<?php

namespace App\Models;

use Database\Factories\FiscalDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalDocument extends Model
{
    /** @use HasFactory<FiscalDocumentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['payload', 'pdf'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'pdf' => 'encrypted', 'last_attempt_at' => 'immutable_datetime', 'issued_at' => 'immutable_datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
