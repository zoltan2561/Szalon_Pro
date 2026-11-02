<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\TenantProvisioner;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_intersect_key($data, array_flip(['name', 'domain', 'status']));
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Tenant, 404);
        app(TenantProvisioner::class)->update($record, array_intersect_key($data, array_flip(['name', 'status'])));

        return $record->refresh();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Az ügyfélpéldány beállításai elmentve.';
    }
}
