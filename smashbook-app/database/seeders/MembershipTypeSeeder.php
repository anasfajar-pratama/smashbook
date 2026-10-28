<?php

namespace Database\Seeders;

use App\Models\MembershipType;
use Illuminate\Database\Seeder;

class MembershipTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Member Bulanan',
                'price' => 250000,
                'duration_days' => 30,
                'discount_pct' => 10,
                'free_hours_per_month' => 2,
                'priority_booking' => true,
                'benefits' => "Booking di muka 30 hari\nPrioritas jam ramai",
            ],
            [
                'name' => 'Member 3 Bulan',
                'price' => 650000,
                'duration_days' => 90,
                'discount_pct' => 12,
                'free_hours_per_month' => 6,
                'priority_booking' => true,
                'benefits' => "Booking di muka 30 hari\nGratis 1× stringing",
            ],
            [
                'name' => 'Member Tahunan',
                'price' => 2200000,
                'duration_days' => 365,
                'discount_pct' => 15,
                'free_hours_per_month' => 8,
                'priority_booking' => true,
                'benefits' => "Gratis 2× stringing\nGratis sewa raket 1×",
            ],
            [
                'name' => 'Komunitas / Korporat',
                'price' => 1500000,
                'duration_days' => 30,
                'discount_pct' => 10,
                'free_hours_per_month' => 0,
                'priority_booking' => true,
                'benefits' => "Booking rutin mingguan\nInvoice bulanan tempo 7 hari\n4 slot per minggu",
            ],
        ];

        foreach ($types as $type) {
            MembershipType::query()->updateOrCreate(['name' => $type['name']], $type + ['is_active' => true]);
        }
    }
}
