<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\URL;

class TenantContext
{
    private ?Tenant $current = null;

    public function id(): int
    {
        return (int) ($this->current?->getKey() ?? 1);
    }

    public function tenant(): ?Tenant
    {
        return $this->current;
    }

    public function isCentralHost(?string $host = null): bool
    {
        return in_array(strtolower($host ?? request()->getHost()), config('tenancy.central_domains'), true);
    }

    public function run(Tenant $tenant, callable $callback, ?string $rootUrl = null): mixed
    {
        $previous = $this->current;
        $previousRoot = URL::to('/');
        $cachedSalon = request()->attributes->get('current_salon');
        $this->current = $tenant;
        request()->attributes->remove('current_salon');
        tenancy()->initialize($tenant);
        URL::forceRootUrl($rootUrl ?? $tenant->url());
        try {
            return $callback();
        } finally {
            $this->current = $previous;
            request()->attributes->remove('current_salon');
            if ($cachedSalon) {
                request()->attributes->set('current_salon', $cachedSalon);
            }
            if ($previous) {
                tenancy()->initialize($previous);
            } else {
                tenancy()->end();
            }
            URL::forceRootUrl($previousRoot);
        }
    }

    public function each(callable $callback): void
    {
        Tenant::where('status', 'active')->orderBy('id')->each(fn (Tenant $tenant) => $this->run($tenant, $callback));
    }
}
