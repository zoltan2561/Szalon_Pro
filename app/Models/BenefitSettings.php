<?php

namespace App\Models;

use Database\Factories\BenefitSettingsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BenefitSettings extends Model
{
    /** @use HasFactory<BenefitSettingsFactory> */
    use HasFactory;

    protected $primaryKey = 'salon_id';

    public $incrementing = false;

    protected $guarded = [];

    protected $attributes = [
        'loyalty_points_per_visit' => 1,
        'loyalty_reward_threshold' => 10,
        'loyalty_reward_label' => 'Hűségjutalom',
    ];

    protected function casts(): array
    {
        return ['loyalty_points_per_visit' => 'integer', 'loyalty_reward_threshold' => 'integer'];
    }

    public static function current(): self
    {
        return self::firstOrNew(['salon_id' => 1]);
    }
}
