<?php

namespace App\Providers\Filament;

use App\Filament\SuperAdmin\Resources\TenantResource;
use App\Http\Middleware\SuperAdminAccess;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

class SuperAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('super')
            ->path('foadmin')
            ->brandName('SzalonPRO · Főadmin')
            ->login()
            ->homeUrl(fn (): string => TenantResource::getUrl('index'))
            ->colors(['primary' => Color::Rose])
            ->resources([TenantResource::class])
            ->middleware(['web', DisableBladeIconComponents::class, DispatchServingFilamentEvent::class])
            ->authMiddleware([Authenticate::class, SuperAdminAccess::class], isPersistent: true);
    }
}
