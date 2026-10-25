<?php

namespace App\Models;

use Database\Factories\GiftVoucherFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftVoucher extends Model
{
    /** @use HasFactory<GiftVoucherFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['public_token', 'public_token_hash', 'buyer_name'];

    protected function casts(): array
    {
        return ['public_token' => 'encrypted', 'buyer_name' => 'encrypted', 'expires_at' => 'immutable_datetime', 'voided_at' => 'immutable_datetime'];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class);
    }
}
