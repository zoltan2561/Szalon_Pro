<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\DatabasePresenceVerifier;

class TenantPresenceVerifier extends DatabasePresenceVerifier
{
    protected function table($table): Builder
    {
        $query = parent::table($table);
        if (in_array($table, config('tenancy.scoped_tables'), true)) {
            $query->where($table.'.tenant_id', app(TenantContext::class)->id());
        }

        return $query;
    }
}
