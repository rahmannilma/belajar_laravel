<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'branch_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    public function isAdmin(): bool
    {
        return $this->isOwner();
    }

    public function canManageProducts(): bool
    {
        return $this->isOwner();
    }

    public function canManageUsers(): bool
    {
        return $this->isOwner();
    }

    public function canViewReports(): bool
    {
        return in_array($this->role, ['owner', 'cashier']);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCashiers($query)
    {
        return $query->where('role', 'cashier');
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'owner' => 'Pemilik',
            'cashier' => 'Kasir',
            default => $this->role,
        };
    }
}
