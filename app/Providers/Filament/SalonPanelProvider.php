<?php

namespace App\Providers\Filament;

use App\Filament\Salon\Pages\Workspace;
use App\Models\Salon;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

class SalonPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('salon')
            ->path('szalon-admin')
            ->brandName(fn (): string => Salon::current()->name)
            ->login()
            ->colors(['primary' => Color::Rose])
            ->pages([Workspace::class])
            ->middleware(['web', DisableBladeIconComponents::class, DispatchServingFilamentEvent::class])
            ->authMiddleware([Authenticate::class], isPersistent: true);
    }
}
