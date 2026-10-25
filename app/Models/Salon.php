<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salon extends Model
{
    use HasFactory;

    protected $table = 'salons';

    protected $guarded = ['id'];

    protected $hidden = ['billing_agent_key'];

    protected function casts(): array
    {
        return ['opening_hours' => 'array', 'gallery' => 'array', 'appearance' => 'array', 'features' => 'array', 'published' => 'boolean', 'demo' => 'boolean', 'billing_agent_key' => 'encrypted', 'billing_sending_enabled' => 'boolean', 'billing_approved_at' => 'immutable_datetime'];
    }

    public function featureEnabled(string $key): bool
    {
        return (bool) ($this->features[$key] ?? false);
    }

    public static function current(): self
    {
        if (app()->runningInConsole()) {
            return self::findOrFail(1);
        }
        $request = request();
        $salon = $request->attributes->get('current_salon');
        if (! $salon instanceof self) {
            $salon = self::findOrFail(1);
            $request->attributes->set('current_salon', $salon);
        }

        return $salon;
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            if (! app()->runningInConsole()) {
                request()->attributes->remove('current_salon');
            }
        });
    }
}
