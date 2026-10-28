<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Member;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private InventoryService $inventory,
        private CashService $cash,
        private MemberService $members,
        private PaymentService $payments,
        private AuditLogger $audit,
    ) {}

    public function checkout(
        User $cashier,
        array $items,
        ?Member $member,
        array $payment,
        float $manualDiscount = 0,
        ?string $discountReason = null,
        ?string $ownerPin = null,
        ?int $bookingId = null,
        ?string $notes = null
    ): array {
        $merged = [];
        foreach ($items as $item) {
            $id = (int) ($item['product_id'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);
            if ($id > 0 && $qty > 0) {
                $merged[$id] = ($merged[$id] ?? 0) + $qty;
            }
        }

        if (empty($merged)) {
            throw ValidationException::withMessages(['items' => 'Keranjang masih kosong.']);
        }

        return DB::transaction(function () use ($cashier, $merged, $member, $payment, $manualDiscount, $discountReason, $ownerPin, $bookingId, $notes) {
            $products = Product::query()
                ->whereIn('id', array_keys($merged))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0.0;
            $lines = [];

            foreach ($merged as $productId => $qty) {
                $product = $products->get($productId);
                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages(['items' => 'Produk tidak ditemukan atau tidak aktif.']);
                }

                if ($qty > $product->stock_qty && ! $product->allow_negative) {
                    throw ValidationException::withMessages([
                        'items' => 'Stok '.$product->name.' tersisa '.$product->stock_qty.' '.$product->base_unit.'.',
                    ]);
                }

                $lineSubtotal = (float) $product->sell_price * $qty;
                $subtotal += $lineSubtotal;
                $lines[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'unit_price' => (float) $product->sell_price,
                    'cost_price' => (float) $product->cost_price,
                    'amount' => $lineSubtotal,
                ];
            }

            $manualDiscount = max($manualDiscount, 0);

            $discountLimitPct = (float) setting('discount_limit_pct', 20);
            if ($manualDiscount > 0 && $manualDiscount > $subtotal * ($discountLimitPct / 100)) {
                if (! $discountReason) {
                    throw ValidationException::withMessages(['discount_reason' => 'Diskon melebihi batas wajib disertai alasan.']);
                }

                $owner = User::query()->where('role', 'owner')->where('is_active', true)->get()
                    ->first(fn ($candidate) => $candidate->verifyPin($ownerPin));

                if (! $owner) {
                    throw ValidationException::withMessages(['owner_pin' => 'PIN Owner tidak valid untuk diskon di atas batas.']);
                }
            }

            $discount = $manualDiscount;
            if ($discount > $subtotal) {
                throw ValidationException::withMessages(['discount_amount' => 'Diskon melebihi subtotal.']);
            }

            $tax = 0.0;
            $total = $subtotal - $discount + $tax;

            $qris = max((float) ($payment['qris'] ?? 0), 0);
            $transfer = max((float) ($payment['transfer'] ?? 0), 0);
            $debit = max((float) ($payment['debit'] ?? 0), 0);
            $deposit = max((float) ($payment['deposit'] ?? 0), 0);
            $nonCash = $qris + $transfer + $debit + $deposit;

            if ($nonCash > $total + 0.01) {
                throw ValidationException::withMessages(['payment' => 'Pembayaran non-tunai melebihi total transaksi.']);
            }

            $cashApplied = max(round($total - $nonCash, 2), 0);
            $cashReceived = max((float) ($payment['cash_received'] ?? $cashApplied), 0);

            if ($cashReceived + 0.01 < $cashApplied) {
                throw ValidationException::withMessages(['payment' => 'Uang tunai diterima kurang dari jumlah yang harus dibayar.']);
            }

            $change = max(round($cashReceived - $cashApplied, 2), 0);

            if ($cashApplied > 0) {
                $this->cash->requireActiveSession($cashier);
            }

            if ($deposit > 0) {
                if (! $member) {
                    throw ValidationException::withMessages(['member_id' => 'Pembayaran deposit memerlukan member.']);
                }
                if ((float) $member->deposit_balance < $deposit) {
                    throw ValidationException::withMessages(['deposit' => 'Saldo deposit member tidak mencukupi.']);
                }
            }

            $booking = $bookingId ? Booking::find($bookingId) : null;
            if ($booking && $booking->status === 'cancelled') {
                throw ValidationException::withMessages(['booking_id' => 'Booking sudah dibatalkan.']);
            }

            $sale = PosSale::create([
                'code' => $this->numbers->next('TRX', now()->format('Ymd'), 4),
                'cash_session_id' => $this->cash->activeSession($cashier)?->id,
                'member_id' => $member?->id,
                'booking_id' => $booking?->id,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'paid_amount' => $total,
                'payment_status' => 'paid',
                'status' => 'paid',
                'cashier_id' => $cashier->id,
                'notes' => $notes,
            ]);

            foreach ($lines as $line) {
                PosSaleItem::create([
                    'pos_sale_id' => $sale->id,
                    'product_id' => $line['product']->id,
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'discount' => 0,
                    'cost_price_snapshot' => $line['cost_price'],
                    'amount' => $line['amount'],
                ]);

                $this->inventory->move(
                    $line['product'],
                    'out',
                    $line['qty'],
                    $line['cost_price'],
                    'pos',
                    $sale->id,
                    'penjualan_pos',
                    'Penjualan '.$sale->code,
                    $cashier
                );
            }

            if ($deposit > 0) {
                $this->members->useDeposit($member, $deposit, 'pos', $sale->id, $cashier);
                $this->payments->record('pos', $sale->id, 'deposit', $deposit, $cashier, null, null, 'Pembayaran POS dengan deposit');
            }

            $createdPayments = [];
            foreach (['qris' => $qris, 'transfer' => $transfer, 'debit' => $debit] as $method => $amount) {
                if ($amount > 0) {
                    $createdPayments[] = $this->payments->record('pos', $sale->id, $method, $amount, $cashier, $payment['reference_no'] ?? null, null, 'Pembayaran POS '.$sale->code);
                }
            }

            if ($cashApplied > 0) {
                $createdPayments[] = $this->payments->record('pos', $sale->id, 'cash', $cashApplied, $cashier, null, null, 'Pembayaran tunai POS '.$sale->code);
            }

            $this->audit->log('pos.checkout', 'pos_sale', $sale->id, null, [
                'total_amount' => $total,
                'discount_amount' => $discount,
                'cash_applied' => $cashApplied,
                'change' => $change,
            ]);

            return [
                'sale' => $sale->load('items.product', 'payments', 'member', 'cashier'),
                'change' => $change,
                'cash_applied' => $cashApplied,
                'cash_received' => $cashReceived,
                'payments' => $createdPayments,
            ];
        });
    }

    public function void(PosSale $sale, User $user, string $reason, string $ownerPin, bool $restoreStock = true): PosSale
    {
        if ($sale->status === 'void') {
            throw ValidationException::withMessages(['sale' => 'Transaksi ini sudah di-void.']);
        }

        if (! $sale->created_at->isToday()) {
            throw ValidationException::withMessages(['sale' => 'Void hanya dapat dilakukan pada hari transaksi yang sama.']);
        }

        if ($sale->cashier_id !== $user->id && ! $user->isAdmin()) {
            throw ValidationException::withMessages(['sale' => 'Hanya kasir transaksi atau admin yang dapat melakukan void.']);
        }

        $owner = User::query()->where('role', 'owner')->where('is_active', true)->get()
            ->first(fn ($candidate) => $candidate->verifyPin($ownerPin));

        if (! $owner) {
            throw ValidationException::withMessages(['owner_pin' => 'PIN Owner tidak valid.']);
        }

        $cashAmount = (float) $sale->payments()->where('method', 'cash')->sum('amount');

        if ($cashAmount > 0) {
            $session = $sale->cashSession;
            if (! $session || $session->status !== 'open') {
                throw ValidationException::withMessages(['sale' => 'Sesi kas transaksi sudah ditutup. Void tunai harus diselesaikan lewat jurnal penyesuaian Owner.']);
            }
        }

        return DB::transaction(function () use ($sale, $user, $reason, $restoreStock, $cashAmount) {
            if ($restoreStock) {
                foreach ($sale->items as $item) {
                    $this->inventory->move(
                        $item->product,
                        'in',
                        $item->qty,
                        (float) $item->cost_price_snapshot,
                        'pos_void',
                        $sale->id,
                        'void_penjualan',
                        'Void '.$sale->code.': '.$reason,
                        $user
                    );
                }
            }

            if ($cashAmount > 0) {
                $this->cash->recordCashOut(
                    $cashAmount,
                    'refund',
                    'Void transaksi '.$sale->code,
                    'pos_void',
                    $sale->id,
                    $user,
                    null,
                    $sale->cashSession
                );
            }

            $before = $sale->toArray();
            $sale->update([
                'status' => 'void',
                'payment_status' => 'void',
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by' => $user->id,
            ]);

            $this->audit->log('pos.void', 'pos_sale', $sale->id, $before, [
                'status' => 'void',
                'reason' => $reason,
                'restore_stock' => $restoreStock,
            ], $reason);

            return $sale->refresh();
        });
    }
}
