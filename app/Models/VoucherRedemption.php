<?php

namespace App\Models;

use Database\Factories\VoucherRedemptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherRedemption extends Model
{
    /** @use HasFactory<VoucherRedemptionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reversed_at' => 'immutable_datetime'];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(GiftVoucher::class, 'gift_voucher_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
