<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtSlot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'slot_date' => 'date',
            'expires_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }
}
