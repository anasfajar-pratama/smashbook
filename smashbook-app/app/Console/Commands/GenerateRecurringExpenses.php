<?php

namespace App\Console\Commands;

use App\Services\PurchaseOrderService;
use Illuminate\Console\Command;

class GenerateRecurringExpenses extends Command
{
    protected $signature = 'smashbook:recurring-expenses';

    protected $description = 'Buat draft PO dari template biaya rutin bulanan';

    public function handle(PurchaseOrderService $orders): int
    {
        $count = $orders->generateRecurringExpenses();
        $this->info("{$count} draft PO biaya rutin dibuat.");

        return self::SUCCESS;
    }
}
