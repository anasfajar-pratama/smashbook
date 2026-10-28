<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\CourtPriceRule;
use Illuminate\Database\Seeder;

class CourtSeeder extends Seeder
{
    public function run(): void
    {
        $courts = [
            ['code' => 'C1', 'name' => 'Lapangan 1', 'type' => 'premium', 'surface' => 'Karpet Vinyl', 'capacity' => 4, 'sort_order' => 1, 'premium' => true],
            ['code' => 'C2', 'name' => 'Lapangan 2', 'type' => 'premium', 'surface' => 'Karpet Vinyl', 'capacity' => 4, 'sort_order' => 2, 'premium' => true],
            ['code' => 'C3', 'name' => 'Lapangan 3', 'type' => 'standar', 'surface' => 'Semen', 'capacity' => 4, 'sort_order' => 3, 'premium' => false],
            ['code' => 'C4', 'name' => 'Lapangan 4', 'type' => 'standar', 'surface' => 'Semen', 'capacity' => 4, 'sort_order' => 4, 'premium' => false],
        ];

        foreach ($courts as $data) {
            $premium = $data['premium'];
            unset($data['premium']);

            $court = Court::query()->updateOrCreate(['code' => $data['code']], $data + ['is_active' => true]);

            $rules = $premium
                ? [
                    ['weekday', '07:00', '16:00', 60000, 'Off-Peak'],
                    ['weekday', '16:00', '22:00', 100000, 'Prime'],
                    ['weekday', '22:00', '24:00', 75000, 'Night'],
                    ['weekend', '07:00', '24:00', 110000, 'Weekend'],
                ]
                : [
                    ['weekday', '07:00', '16:00', 50000, 'Off-Peak'],
                    ['weekday', '16:00', '22:00', 85000, 'Prime'],
                    ['weekday', '22:00', '24:00', 70000, 'Night'],
                    ['weekend', '07:00', '24:00', 90000, 'Weekend'],
                ];

            $court->priceRules()->delete();
            foreach ($rules as [$day, $start, $end, $price, $label]) {
                CourtPriceRule::create([
                    'court_id' => $court->id,
                    'day_type' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                    'price_per_hour' => $price,
                    'label' => $label,
                ]);
            }
        }
    }
}
