<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\TenantResource\Pages;
use App\Http\Controllers\TenantImpersonationController;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use App\Services\TenantProvisioner;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use UnitEnum;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $slug = 'tenants';

    protected static ?string $modelLabel = 'ügyfélpéldány';

    protected static ?string $pluralModelLabel = 'Ügyfélpéldányok';

    protected static ?string $navigationLabel = 'Ügyfélpéldányok';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('slug')->label('Aldomain neve')->required()->maxLength(40)
                ->regex('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/')
                ->helperText('Kisbetű, szám és kötőjel. Létrehozás után az aldomain nem módosítható.')
                ->visibleOn('create'),
            TextInput::make('name')->label('Szalon neve')->required()->maxLength(120),
            TextInput::make('admin_email')->label('Tulajdonos e-mail-címe')->email()->required()->maxLength(200)
                ->helperText('Az első belépéshez jelszó-visszaállítást kell kérni az új példány belépőoldalán.')
                ->visibleOn('create'),
            TextInput::make('domain')->label('Webcím')->disabled()->dehydrated(false)->visibleOn('edit'),
            Select::make('status')->label('Elérhetőség')->options(['active' => 'Aktív', 'suspended' => 'Felfüggesztve'])
                ->required()->visibleOn('edit')->native(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Tenant $record): ?string => (int) $record->id === 1 ? null : self::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label('Szalon')->searchable()->sortable(),
                TextColumn::make('domain')->label('Webcím')->searchable()
                    ->url(fn (Tenant $record): string => $record->url())->openUrlInNewTab(),
                TextColumn::make('admin_email')->label('Tulajdonos e-mail-címe')
                    ->state(fn (Tenant $record): ?string => User::withoutGlobalScopes()->where('tenant_id', $record->id)->where('role', 'owner')->where('is_demo_viewer', false)->value('email')),
                TextColumn::make('status')->label('Elérhetőség')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? 'Aktív' : 'Felfüggesztve')
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'warning'),
                TextColumn::make('created_at')->label('Létrehozva')->dateTime('Y. m. d. H:i')->timezone('Europe/Budapest')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([SelectFilter::make('status')->label('Elérhetőség')->options(['active' => 'Aktív', 'suspended' => 'Felfüggesztve'])])
            ->recordActions([ActionGroup::make([
                EditAction::make()->label('Szerkesztés'),
                Action::make('suspend')->label('Felfüggesztés')->color('warning')->requiresConfirmation()
                    ->modalDescription('A szalon és az admin átmenetileg nem lesz elérhető. Az adatok megmaradnak.')
                    ->visible(fn (Tenant $record): bool => $record->status === 'active' && $record->slug !== 'demo')
                    ->authorize(fn (): bool => self::canViewAny())
                    ->action(fn (Tenant $record) => app(TenantProvisioner::class)->update($record, ['name' => $record->name, 'status' => 'suspended']))
                    ->successNotificationTitle('Az ügyfélpéldány felfüggesztve.'),
                Action::make('activate')->label('Újraaktiválás')->color('success')->requiresConfirmation()
                    ->visible(fn (Tenant $record): bool => $record->status === 'suspended')
                    ->authorize(fn (): bool => self::canViewAny())
                    ->action(fn (Tenant $record) => app(TenantProvisioner::class)->update($record, ['name' => $record->name, 'status' => 'active']))
                    ->successNotificationTitle('Az ügyfélpéldány újra aktív.'),
                Action::make('impersonate')->label('Belépés a szalonba')->requiresConfirmation()
                    ->modalDescription('A szalon tulajdonosaként lépsz be. A belépés naplózva lesz, és egyszer használható hivatkozással történik.')
                    ->visible(fn (Tenant $record): bool => $record->status === 'active')
                    ->authorize(fn (): bool => self::canViewAny())
                    ->action(function (Tenant $record, Component $livewire): void {
                        $response = app(TenantImpersonationController::class)->start(request(), $record);
                        $livewire->redirect($response->getTargetUrl());
                    }),
            ])->label('Műveletek')])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Még nincs ügyfélpéldány')
            ->emptyStateDescription('Az új példány saját beállításokkal és üres vendégkörrel indul.');
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        if ($action === 'update' && (int) $record?->id === 1) {
            return Response::deny();
        }
        $allowed = auth()->user()?->is_super_admin && (int) auth()->user()->tenant_id === 1
            && ! auth()->user()->isDemoViewer() && app(TenantContext::class)->isCentralHost();

        return $allowed && in_array($action, ['viewAny', 'view', 'create', 'update'], true) ? Response::allow() : Response::deny();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
