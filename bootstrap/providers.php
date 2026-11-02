<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\SalonPanelProvider;
use App\Providers\Filament\SuperAdminPanelProvider;

return [
    AppServiceProvider::class,
    SuperAdminPanelProvider::class,
    SalonPanelProvider::class,
];
