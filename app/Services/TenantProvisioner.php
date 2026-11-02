<?php

namespace App\Services;

use App\Models\Salon;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TenantProvisioner
{
    public function create(string $slug, string $name, string $email): Tenant
    {
        $slug = strtolower(trim($slug));
        $data = Validator::make(compact('slug', 'name', 'email'), [
            'slug' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', Rule::notIn(['www', 'admin', 'foadmin', 'api', 'mail', 'demo', 'barber', 'nails', 'salon', 'test', 'localhost']), Rule::unique('tenants', 'slug')],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:200'],
        ])->validate();

        $domain = $data['slug'].'.'.config('tenancy.base_domain');
        $demoHosts = array_filter(array_map(fn (string $url): ?string => parse_url($url, PHP_URL_HOST), config('salon.demo_urls', [])));
        $reservedDomains = array_map(fn (string $host): string => strtolower(trim($host)), [...config('tenancy.central_domains'), ...config('tenancy.demo_domains'), ...$demoHosts]);
        if (in_array(strtolower($domain), $reservedDomains, true) || Tenant::where('domain', $domain)->exists()) {
            throw ValidationException::withMessages(['slug' => 'Ez a webcím már használatban van. Válassz másik aldomainnevet.']);
        }

        return DB::transaction(function () use ($data): Tenant {
            $tenant = Tenant::create(['slug' => $data['slug'], 'name' => $data['name'], 'domain' => $data['slug'].'.'.config('tenancy.base_domain'), 'status' => 'active']);
            app(TenantContext::class)->run($tenant, function () use ($data): void {
                Salon::create(['name' => $data['name'], 'tagline' => 'Idő magadra.', 'email' => '', 'demo' => false, 'published' => true, 'opening_hours' => [], 'appearance' => config('branding.presets.salon.appearance')]);
                User::create(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'password' => Str::random(64), 'role' => 'owner', 'can_manage_bookings' => true, 'can_view_notes' => true]);
            });

            return $tenant;
        });
    }

    public function update(Tenant $tenant, array $data): void
    {
        if ((int) $tenant->id === 1) {
            throw ValidationException::withMessages(['name' => 'A meglévő bemutató a főadminban csak olvasható.']);
        }
        $validated = Validator::make($data, ['name' => ['required', 'string', 'max:120'], 'status' => ['required', Rule::in(['active', 'suspended'])]])->validate();
        if ((int) $tenant->id === 1 && $validated['status'] !== 'active') {
            throw ValidationException::withMessages(['status' => 'A meglévő bemutató nem függeszthető fel.']);
        }
        DB::transaction(function () use ($tenant, $validated): void {
            $tenant->update($validated);
            app(TenantContext::class)->run($tenant, fn () => Salon::current()->update(['name' => $validated['name']]));
        });
    }
}
