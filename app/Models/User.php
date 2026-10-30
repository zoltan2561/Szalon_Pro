<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'staff_id', 'can_manage_bookings', 'can_view_notes'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime', 'can_manage_bookings' => 'boolean', 'can_view_notes' => 'boolean', 'is_demo_viewer' => 'boolean'];
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
