<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashDepositBank extends Model
{
    protected $table = 'cash_deposits_bank';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'deposit_date' => 'date',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
