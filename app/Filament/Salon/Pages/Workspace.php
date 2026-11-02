<?php

namespace App\Filament\Salon\Pages;

use Filament\Pages\Dashboard;

class Workspace extends Dashboard
{
    protected static ?string $title = 'Saját szalonom';

    protected static ?string $navigationLabel = 'Saját szalonom';

    protected string $view = 'filament.salon.pages.workspace';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'owner' && ! auth()->user()->isDemoViewer();
    }
}
