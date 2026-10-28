<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;

class PaymentService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private CashService $cash,
        private AuditLogger $audit,
    ) {}

    public function record(
        string $refType,
        int $refId,
        string $method,
        float $amount,
        ?User $cashier = null,
        ?string $reference = null,
        ?string $notes = null,
        string $description = ''
    ): Payment {
        $cashier ??= auth()->user();

        $payment = Payment::create([
            'code' => $this->numbers->next('PAY', now()->format('Ymd'), 4),
            'ref_type' => $refType,
            'ref_id' => $refId,
            'method' => $method,
            'amount' => $amount,
            'paid_at' => now(),
            'cashier_id' => $cashier?->id,
            'reference_no' => $reference,
            'notes' => $notes,
        ]);

        $this->cash->recordPayment($payment, $description ?: ucfirst($refType).' pembayaran');

        return $payment;
    }
}
