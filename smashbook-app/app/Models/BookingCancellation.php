<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingCancellation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
            'refund_amount' => 'decimal:2',
            'refund_pct' => 'decimal:2',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
