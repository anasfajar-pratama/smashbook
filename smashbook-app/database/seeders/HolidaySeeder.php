<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        Holiday::query()->updateOrCreate(['date' => '2026-12-25'], ['name' => 'Natal', 'is_peak' => true]);
        Holiday::query()->updateOrCreate(['date' => '2027-01-01'], ['name' => 'Tahun Baru', 'is_peak' => true]);
        Holiday::query()->updateOrCreate(['date' => '2027-03-19'], ['name' => 'Nyepi', 'is_peak' => false]);
    }
}
