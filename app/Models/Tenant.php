<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    public $incrementing = true;

    protected $keyType = 'int';

    public static function getCustomColumns(): array
    {
        return ['id', 'slug', 'name', 'domain', 'status', 'created_at', 'updated_at'];
    }

    public function url(): string
    {
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $port = parse_url(config('app.url'), PHP_URL_PORT);

        return $scheme.'://'.$this->domain.($port && app()->environment(['local', 'testing']) ? ':'.$port : '');
    }
}
