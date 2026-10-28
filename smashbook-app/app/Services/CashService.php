<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private AuditLogger $audit,
    ) {}

    public function activeSession(?User $user = null): ?CashSession
    {
        $user ??= auth()->user();

        if (! $user) {
            return null;
        }

        return CashSession::query()
            ->where('cashier_id', $user->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    public function requireActiveSession(?User $user = null): CashSession
    {
        $session = $this->activeSession($user);

        if (! $session) {
            throw ValidationException::withMessages([
                'cash' => 'Transaksi tunai memerlukan sesi kas terbuka. Buka shift terlebih dahulu.',
            ]);
        }

        return $session;
    }

    public function open(User $user, string $shift, float $openingBalance): CashSession
    {
        if ($this->activeSession($user)) {
            throw ValidationException::withMessages(['cash' => 'Anda masih memiliki sesi kas terbuka.']);
        }

        return DB::transaction(function () use ($user, $shift, $openingBalance) {
            $session = CashSession::create([
                'code' => $this->numbers->sessionCode($shift),
                'shift' => $shift,
                'cashier_id' => $user->id,
                'opened_at' => now(),
                'opening_balance' => $openingBalance,
                'status' => 'open',
            ]);

            if ($openingBalance > 0) {
                $this->insertMovement(
                    $session,
                    'in',
                    'modal_awal',
                    $openingBalance,
                    'Modal awal shift',
                    'cash_session',
                    $session->id,
                    null,
                    $user->id
                );
            }

            $this->audit->log('cash.open', 'cash_session', $session->id, null, [
                'shift' => $shift,
                'opening_balance' => $openingBalance,
            ]);

            return $session->refresh();
        });
    }

    public function recordCashIn(
        float $amount,
        string $category,
        string $description,
        ?string $refType = null,
        ?int $refId = null,
        ?int $paymentId = null,
        ?User $user = null,
        ?string $attachment = null
    ): CashMovement {
        $user ??= auth()->user();
        $session = $this->requireActiveSession($user);

        return $this->insertMovement($session, 'in', $category, $amount, $description, $refType, $refId, $paymentId, $user?->id, $attachment);
    }

    public function recordCashOut(
        float $amount,
        string $category,
        string $description,
        ?string $refType = null,
        ?int $refId = null,
        ?User $user = null,
        ?string $attachment = null,
        ?CashSession $session = null
    ): CashMovement {
        $user ??= auth()->user();
        $session ??= $this->requireActiveSession($user);

        return $this->insertMovement($session, 'out', $category, $amount, $description, $refType, $refId, null, $user?->id, $attachment);
    }

    public function insertMovement(
        CashSession $session,
        string $type,
        string $category,
        float $amount,
        string $description,
        ?string $refType = null,
        ?int $refId = null,
        ?int $paymentId = null,
        ?int $userId = null,
        ?string $attachment = null
    ): CashMovement {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nominal kas harus lebih dari nol.']);
        }

        if ($session->status !== 'open') {
            throw ValidationException::withMessages(['cash' => 'Sesi kas sudah ditutup.']);
        }

        return CashMovement::create([
            'cash_session_id' => $session->id,
            'type' => $type,
            'category' => $category,
            'amount' => $amount,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'payment_id' => $paymentId,
            'description' => $description,
            'attachment' => $attachment,
            'created_by' => $userId,
        ]);
    }

    public function recordPayment(Payment $payment, string $description): ?CashMovement
    {
        if ($payment->method !== 'cash') {
            return null;
        }

        $cashier = $payment->cashier_id ? User::find($payment->cashier_id) : auth()->user();
        $session = $this->requireActiveSession($cashier);

        $category = match ($payment->ref_type) {
            'pos' => 'pos',
            'booking' => 'booking',
            'deposit' => 'deposit',
            'subscription' => 'membership',
            default => 'lain_lain',
        };

        return $this->insertMovement(
            $session,
            'in',
            $category,
            (float) $payment->amount,
            $description,
            $payment->ref_type,
            $payment->ref_id,
            $payment->id,
            $payment->cashier_id
        );
    }

    public function close(CashSession $session, float $physicalBalance, ?string $notes = null, ?User $verifier = null): CashSession
    {
        if ($session->status !== 'open') {
            throw ValidationException::withMessages(['cash' => 'Sesi kas ini sudah ditutup.']);
        }

        $expected = $session->expectedBalance();
        $difference = $physicalBalance - $expected;
        $tolerance = (float) setting('cash_difference_tolerance', 10000);

        if (abs($difference) > $tolerance) {
            if (! $notes) {
                throw ValidationException::withMessages([
                    'notes' => 'Selisih melebihi toleransi. Catatan alasan wajib diisi.',
                ]);
            }

            if (! $verifier || ! $verifier->isOwner()) {
                throw ValidationException::withMessages([
                    'verifier' => 'Selisih melebihi toleransi. Verifikasi Owner wajib dilakukan.',
                ]);
            }
        }

        $before = $session->toArray();

        $session->update([
            'closed_at' => now(),
            'expected_balance' => $expected,
            'physical_balance' => $physicalBalance,
            'difference' => $difference,
            'status' => 'closed',
            'notes' => $notes,
            'verified_by' => $verifier?->id ?? (abs($difference) <= $tolerance ? auth()->id() : null),
        ]);

        $this->audit->log('cash.close', 'cash_session', $session->id, $before, [
            'expected_balance' => $expected,
            'physical_balance' => $physicalBalance,
            'difference' => $difference,
        ], $notes);

        return $session->refresh();
    }

    public function paymentsByMethod(CashSession $session): array
    {
        $end = $session->closed_at ?? now();

        $rows = Payment::query()
            ->where('cashier_id', $session->cashier_id)
            ->whereBetween('paid_at', [$session->opened_at, $end])
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->all();

        return [
            'cash' => (float) ($rows['cash'] ?? 0),
            'qris' => (float) ($rows['qris'] ?? 0),
            'transfer' => (float) ($rows['transfer'] ?? 0),
            'debit' => (float) ($rows['debit'] ?? 0),
            'deposit' => (float) ($rows['deposit'] ?? 0),
        ];
    }

    public function recap(Carbon $date, ?User $cashier = null): array
    {
        $sessions = CashSession::query()
            ->with('cashier')
            ->whereDate('opened_at', $date->toDateString())
            ->when($cashier, fn ($q) => $q->where('cashier_id', $cashier->id))
            ->orderBy('opened_at')
            ->get();

        $sessionIds = $sessions->pluck('id');

        $cashIn = (float) CashMovement::query()
            ->whereIn('cash_session_id', $sessionIds)
            ->where('type', 'in')
            ->where('category', '!=', 'modal_awal')
            ->sum('amount');

        $cashOut = (float) CashMovement::query()
            ->whereIn('cash_session_id', $sessionIds)
            ->where('type', 'out')
            ->sum('amount');

        $nonCash = (float) Payment::query()
            ->whereDate('paid_at', $date->toDateString())
            ->when($cashier, fn ($q) => $q->where('cashier_id', $cashier->id))
            ->whereIn('method', ['qris', 'transfer', 'debit'])
            ->sum('amount');

        $differences = $sessions->where('status', 'closed')->sum('difference');

        return [
            'date' => $date,
            'sessions' => $sessions,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'non_cash' => $nonCash,
            'difference' => (float) $differences,
        ];
    }
}
