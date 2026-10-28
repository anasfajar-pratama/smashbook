<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

class ReleaseExpiredHolds extends Command
{
    protected $signature = 'smashbook:release-holds';

    protected $description = 'Lepas hold booking yang melewati masa berlaku';

    public function handle(BookingService $bookings): int
    {
        $count = $bookings->releaseExpiredHolds();
        $this->info("{$count} hold kedaluwarsa dilepas.");

        return self::SUCCESS;
    }
}
