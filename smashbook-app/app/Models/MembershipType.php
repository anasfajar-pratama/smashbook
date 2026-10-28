<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_pct' => 'decimal:2',
            'priority_booking' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function members()
    {
        return $this->hasMany(Member::class, 'member_type_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(MemberSubscription::class);
    }
}
