<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'pin_hash',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'pin_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public const ROLES = [
        'owner' => 'Owner',
        'admin' => 'Admin',
        'kasir' => 'Kasir',
        'operator' => 'Operator Lapangan',
    ];

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['owner', 'admin'], true);
    }

    public function isCashier(): bool
    {
        return in_array($this->role, ['owner', 'admin', 'kasir'], true);
    }

    public function isOperator(): bool
    {
        return $this->role === 'operator';
    }

    public function canSeeCost(): bool
    {
        return in_array($this->role, ['owner', 'admin'], true);
    }

    public function canApprovePo(float $amount): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        return $this->role === 'admin' && $amount <= (float) setting('po_owner_threshold', 2000000);
    }

    public function verifyPin(?string $pin): bool
    {
        return $pin !== null && $this->pin_hash !== null && Hash::check($pin, $this->pin_hash);
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials ?: 'U';
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? ucfirst($this->role);
    }

    public function cashSessions()
    {
        return $this->hasMany(CashSession::class, 'cashier_id');
    }

    public function activeCashSession()
    {
        return $this->hasOne(CashSession::class, 'cashier_id')->where('status', 'open')->latestOfMany();
    }

    public function posSales()
    {
        return $this->hasMany(PosSale::class, 'cashier_id');
    }
}
