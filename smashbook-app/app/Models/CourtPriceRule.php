<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtPriceRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price_per_hour' => 'decimal:2',
        ];
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }
}
