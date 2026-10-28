<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'birth_date' => 'date',
            'deposit_balance' => 'decimal:2',
            'no_show_count' => 'integer',
        ];
    }

    public function membershipType()
    {
        return $this->belongsTo(MembershipType::class, 'member_type_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(MemberSubscription::class)->latest('end_date');
    }

    public function activeSubscription()
    {
        return $this->hasOne(MemberSubscription::class)
            ->where('status', 'aktif')
            ->whereDate('end_date', '>=', now()->toDateString())
            ->latestOfMany('end_date');
    }

    public function deposits()
    {
        return $this->hasMany(Deposit::class)->latest('created_at');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class)->latest('booking_date');
    }

    public function posSales()
    {
        return $this->hasMany(PosSale::class)->latest('created_at');
    }

    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'blacklist');
    }

    public function effectiveStatus(): string
    {
        if (in_array($this->status, ['suspend', 'blacklist', 'perlu_konfirmasi'], true)) {
            return $this->status;
        }

        $end = $this->activeSubscription?->end_date;
        if (! $end) {
            return $this->status === 'expired' ? 'expired' : 'aktif';
        }

        $days = now()->startOfDay()->diffInDays(Carbon::parse($end)->startOfDay(), false);
        if ($days < 0) {
            return 'expired';
        }

        return $days <= 7 ? 'akan_expired' : 'aktif';
    }

    public function discountPct(): float
    {
        return (float) ($this->activeSubscription?->membershipType?->discount_pct
            ?? $this->membershipType?->discount_pct
            ?? 0);
    }
}
