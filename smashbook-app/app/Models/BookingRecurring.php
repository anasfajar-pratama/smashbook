<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingRecurring extends Model
{
    protected $table = 'booking_recurring';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
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
}
