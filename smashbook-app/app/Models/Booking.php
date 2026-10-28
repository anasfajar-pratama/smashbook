<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'base_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function slots()
    {
        return $this->hasMany(BookingSlot::class);
    }

    public function cancellation()
    {
        return $this->hasOne(BookingCancellation::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'ref_id')->where('ref_type', 'booking');
    }

    public function posSales()
    {
        return $this->hasMany(PosSale::class);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('booking_date', $date);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['cancelled', 'no_show', 'expired']);
    }
}
