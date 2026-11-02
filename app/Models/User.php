<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\TenantContext;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'staff_id', 'can_manage_bookings', 'can_view_notes'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use BelongsToTenant;
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime', 'can_manage_bookings' => 'boolean', 'can_view_notes' => 'boolean', 'is_demo_viewer' => 'boolean', 'is_super_admin' => 'boolean', 'tenant_id' => 'integer'];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        $context = app(TenantContext::class);
        if ($panel->getId() === 'super') {
            return $this->is_super_admin && $context->isCentralHost() && (int) $this->tenant_id === 1;
        }

        return $panel->getId() === 'salon' && $this->role === 'owner' && ! $this->isDemoViewer()
            && (int) $this->tenant_id === $context->id() && $context->tenant()?->status === 'active';
    }

    public function getEmailForPasswordReset(): string
    {
        return (int) $this->tenant_id === 1 ? $this->email : 'tenant:'.$this->tenant_id.':'.hash('sha256', mb_strtolower($this->email));
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return asset('assets/branding/sp-mark.png');
    }

    public function isDemoViewer(): bool
    {
        return (bool) $this->is_demo_viewer;
    }

    public function canViewGuestNotes(): bool
    {
        return ! $this->isDemoViewer() && ($this->role === 'owner' || $this->can_view_notes);
    }
}
