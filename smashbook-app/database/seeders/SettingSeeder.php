<?php

namespace Database\Seeders;

use App\Services\SettingService;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = app(SettingService::class);

        $values = [
            'venue_name' => 'GOR Smash Arena',
            'venue_address' => 'Jl. Olahraga No. 12, Bandung',
            'venue_phone' => '0812-3456-7890',
            'receipt_footer' => 'Terima kasih, selamat berolahraga!',
            'opening_time' => '07:00',
            'closing_time' => '24:00',
            'hold_minutes' => 15,
            'public_advance_days' => 14,
            'member_advance_days' => 30,
            'dp_percent' => 50,
            'cash_difference_tolerance' => 10000,
            'discount_limit_pct' => 20,
            'po_admin_threshold' => 500000,
            'po_owner_threshold' => 2000000,
            'po_urgent_cash_threshold' => 250000,
            'po_cash_receipt_threshold' => 500000,
        ];

        foreach ($values as $key => $value) {
            $settings->set($key, $value);
        }
    }
}
