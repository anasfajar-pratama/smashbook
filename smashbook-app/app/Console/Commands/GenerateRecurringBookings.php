<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

class GenerateRecurringBookings extends Command
{
    protected $signature = 'smashbook:recurring-bookings';

    protected $description = 'Buat booking dari jadwal rutin yang aktif';

    public function handle(BookingService $bookings): int
    {
        $count = $bookings->generateRecurringBookings();
        $this->info("{$count} booking rutin dibuat.");

        return self::SUCCESS;
    }
}
