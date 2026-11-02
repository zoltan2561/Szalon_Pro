<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantResource;
use App\Services\TenantProvisioner;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        return app(TenantProvisioner::class)->create($data['slug'], $data['name'], $data['admin_email']);
    }

    protected function getRedirectUrl(): string
    {
        return TenantResource::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Az ügyfélpéldány elkészült.';
    }
}
