<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashSession extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_balance' => 'decimal:2',
            'expected_balance' => 'decimal:2',
            'physical_balance' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function movements()
    {
        return $this->hasMany(CashMovement::class);
    }

    public function sales()
    {
        return $this->hasMany(PosSale::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function totalIn(): float
    {
        return (float) $this->movements()->where('type', 'in')->sum('amount');
    }

    public function totalOut(): float
    {
        return (float) $this->movements()->where('type', 'out')->sum('amount');
    }

    public function expectedBalance(): float
    {
        return $this->totalIn() - $this->totalOut();
    }
}
