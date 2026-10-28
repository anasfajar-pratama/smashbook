<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosSale extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(PosSaleItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'ref_id')->where('ref_type', 'pos');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function cashSession()
    {
        return $this->belongsTo(CashSession::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
