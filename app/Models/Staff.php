<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Staff extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'staff';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['weekly_hours' => 'array', 'active' => 'boolean'];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->using(TenantPivot::class)->withPivot('tenant_id')->wherePivot('tenant_id', app(TenantContext::class)->id());
    }

    public function googleCalendarConnection(): HasOne
    {
        return $this->hasOne(GoogleCalendarConnection::class);
    }
}
