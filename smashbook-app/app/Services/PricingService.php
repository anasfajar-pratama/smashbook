<?php

namespace App\Services;

use App\Models\Court;
use App\Models\Holiday;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class PricingService
{
    public function dayType(Carbon $date): string
    {
        $holiday = Holiday::query()->whereDate('date', $date->toDateString())->first();

        if ($holiday && $holiday->is_peak) {
            return 'weekend';
        }

        return $date->isWeekend() ? 'weekend' : 'weekday';
    }

    public function quote(
        Court $court,
        Carbon $date,
        string $startTime,
        int $durationMinutes,
        ?Member $member = null
    ): array {
        $opening = setting('opening_time', '07:00');
        $closing = setting('closing_time', '24:00');

        if ($durationMinutes < 30 || $durationMinutes % 30 !== 0) {
            throw ValidationException::withMessages(['duration_minutes' => 'Durasi harus kelipatan 30 menit.']);
        }

        if ($durationMinutes > 480) {
            throw ValidationException::withMessages(['duration_minutes' => 'Durasi maksimal 8 jam per booking.']);
        }

        $startMinutes = $this->toMinutes($startTime);
        $openingMinutes = $this->toMinutes($opening);
        $closingMinutes = $this->toMinutes($closing);
        $endMinutes = $startMinutes + $durationMinutes;

        if ($startMinutes < $openingMinutes || $endMinutes > $closingMinutes) {
            throw ValidationException::withMessages([
                'start_time' => 'Jam booking harus di dalam jam operasional '.jam($opening).'–'.jam($closing).'.',
            ]);
        }

        $dayType = $this->dayType($date);
        $ruleDayType = $dayType === 'weekend' ? 'weekend' : 'weekday';
        $rules = $court->priceRules()
            ->whereIn('day_type', [$ruleDayType, 'all'])
            ->get()
            ->sortBy(fn ($rule) => $this->toMinutes($rule->start_time))
            ->values();

        $slots = [];
        $base = 0.0;

        for ($offset = 0; $offset < $durationMinutes; $offset += 30) {
            $slotMinutes = $startMinutes + $offset;
            $rule = $rules->first(function ($candidate) use ($slotMinutes) {
                return $slotMinutes >= $this->toMinutes($candidate->start_time)
                    && $slotMinutes < $this->toMinutes($candidate->end_time);
            });

            if (! $rule) {
                throw ValidationException::withMessages([
                    'start_time' => 'Tarif belum diatur untuk jam '.$this->fromMinutes($slotMinutes).' pada lapangan ini.',
                ]);
            }

            $price = round(((float) $rule->price_per_hour) / 2, 2);
            $base += $price;
            $slots[] = [
                'time' => $this->fromMinutes($slotMinutes),
                'end' => $this->fromMinutes($slotMinutes + 30),
                'price' => $price,
                'category' => $rule->label,
            ];
        }

        $discountPct = $member?->discountPct() ?? 0;
        $discount = round($base * ($discountPct / 100), 2);

        return [
            'day_type' => $dayType,
            'start_time' => $this->fromMinutes($startMinutes),
            'end_time' => $this->fromMinutes($endMinutes),
            'duration_minutes' => $durationMinutes,
            'slots' => $slots,
            'base_amount' => $base,
            'discount_pct' => $discountPct,
            'discount_amount' => $discount,
            'total_amount' => max($base - $discount, 0),
        ];
    }

    public function slotTimes(string $startTime, int $durationMinutes): array
    {
        $start = $this->toMinutes($startTime);
        $times = [];

        for ($offset = 0; $offset < $durationMinutes; $offset += 30) {
            $times[] = $this->fromMinutes($start + $offset);
        }

        return $times;
    }

    public function toMinutes(?string $time): int
    {
        if (! $time) {
            return 0;
        }

        $parts = explode(':', $time);

        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
    }

    public function fromMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return str_pad((string) $hours, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $mins, 2, '0', STR_PAD_LEFT);
    }

    public function operatingHours(): array
    {
        $opening = $this->toMinutes(setting('opening_time', '07:00'));
        $closing = $this->toMinutes(setting('closing_time', '24:00'));
        $hours = [];

        for ($m = $opening; $m < $closing; $m += 60) {
            $hours[] = $this->fromMinutes($m);
        }

        return $hours;
    }
}
