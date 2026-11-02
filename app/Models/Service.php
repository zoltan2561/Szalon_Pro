<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class)->using(TenantPivot::class)->withPivot('tenant_id')->wherePivot('tenant_id', app(TenantContext::class)->id());
    }
}
