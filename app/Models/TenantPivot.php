<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantPivot extends Pivot
{
    use BelongsToTenant;
}
