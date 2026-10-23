<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salon extends Model
{
    use HasFactory;

    protected $table = 'salons';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['opening_hours' => 'array', 'gallery' => 'array', 'appearance' => 'array', 'features' => 'array', 'published' => 'boolean', 'demo' => 'boolean'];
    }

    public function featureEnabled(string $key): bool
    {
        return (bool) ($this->features[$key] ?? false);
    }

    public static function current(): self
    {
        return self::findOrFail(1);
    }
}
