<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function next(string $prefix, ?string $period = null, int $pad = 4): string
    {
        $periodKey = $period ?? '';

        return DB::transaction(function () use ($prefix, $periodKey, $pad, $period) {
            DB::table('sequences')->insertOrIgnore([
                'prefix' => $prefix,
                'period' => $periodKey,
                'last_number' => 0,
            ]);

            $row = DB::table('sequences')
                ->where('prefix', $prefix)
                ->where('period', $periodKey)
                ->lockForUpdate()
                ->first();

            $next = ((int) $row->last_number) + 1;

            DB::table('sequences')
                ->where('id', $row->id)
                ->update(['last_number' => $next]);

            $number = str_pad((string) $next, $pad, '0', STR_PAD_LEFT);

            return $period === null
                ? $prefix.'-'.$number
                : $prefix.'-'.$period.'-'.$number;
        });
    }

    public function sessionCode(string $shift): string
    {
        $base = 'KAS-'.now()->format('Ymd').'-'.strtoupper($shift);
        $code = $base;
        $i = 2;

        while (DB::table('cash_sessions')->where('code', $code)->exists()) {
            $code = $base.'-'.$i++;
        }

        return $code;
    }
}
