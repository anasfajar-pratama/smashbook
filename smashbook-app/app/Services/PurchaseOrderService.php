<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RecurringExpense;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private InventoryService $inventory,
        private CashService $cash,
        private AuditLogger $audit,
    ) {}

    public function create(array $data, User $user, bool $saveDraft = false): PurchaseOrder
    {
        $items = array_values(array_filter($data['items'] ?? [], fn ($item) => ! empty($item['description']) && (float) ($item['qty'] ?? 0) > 0));

        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'Minimal satu item PO harus diisi.']);
        }

        return DB::transaction(function () use ($data, $user, $saveDraft, $items) {
            $subtotal = 0.0;
            $types = [];

            foreach ($items as $item) {
                $subtotal += (float) $item['qty'] * (float) $item['unit_price'];
                $types[] = $item['item_type'] ?? 'product';
            }

            $discount = (float) ($data['discount_amount'] ?? 0);
            $tax = (float) ($data['tax_amount'] ?? 0);
            $shipping = (float) ($data['shipping_amount'] ?? 0);
            $total = max($subtotal - $discount + $tax + $shipping, 0);

            $poType = count(array_unique($types)) > 1 ? 'mixed' : ($types[0] ?? 'product');
            $priority = $data['priority'] ?? 'normal';
            $paymentMethod = $data['payment_method'] ?? 'cash';

            $status = 'draft';
            if (! $saveDraft) {
                $adminThreshold = (float) setting('po_admin_threshold', 500000);
                $urgentThreshold = (float) setting('po_urgent_cash_threshold', 250000);
                $autoApprove = $user->isAdmin() && (
                    $total <= $adminThreshold
                    || ($priority === 'urgent' && $paymentMethod === 'cash' && $total <= $urgentThreshold)
                );

                $status = $autoApprove ? 'approved' : 'submitted';
            }

            $po = PurchaseOrder::create([
                'code' => $this->numbers->next('PO', now()->format('Ymd'), 3),
                'supplier_id' => $data['supplier_id'] ?? null,
                'supplier_name' => $data['supplier_name'] ?? null,
                'po_type' => $poType,
                'request_date' => $data['request_date'] ?? now()->toDateString(),
                'expected_date' => $data['expected_date'] ?? null,
                'status' => $status,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'shipping_amount' => $shipping,
                'total_amount' => $total,
                'payment_method' => $paymentMethod,
                'priority' => $priority,
                'requested_by' => $user->id,
                'approved_by' => $status === 'approved' ? $user->id : null,
                'approved_at' => $status === 'approved' ? now() : null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'item_type' => $item['item_type'] ?? 'product',
                    'product_id' => ($item['item_type'] ?? 'product') === 'product' ? ($item['product_id'] ?? null) : null,
                    'expense_category' => $item['expense_category'] ?? null,
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'unit' => $item['unit'] ?? null,
                    'unit_price' => $item['unit_price'],
                    'amount' => (float) $item['qty'] * (float) $item['unit_price'],
                ]);
            }

            $this->audit->log('po.create', 'purchase_order', $po->id, null, $po->load('items')->toArray());

            return $po->load('items.product', 'supplier', 'requester');
        });
    }

    public function approve(PurchaseOrder $po, User $user, ?string $note = null): PurchaseOrder
    {
        if ($po->status !== 'submitted') {
            throw ValidationException::withMessages(['po' => 'PO ini tidak dalam status menunggu approval.']);
        }

        if (! $user->canApprovePo((float) $po->total_amount)) {
            throw ValidationException::withMessages(['po' => 'Nominal PO melebihi batas approval Anda.']);
        }

        $po->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'notes' => trim(($po->notes ? $po->notes."\n" : '').($note ? 'Approval: '.$note : '')),
        ]);

        $this->audit->log('po.approve', 'purchase_order', $po->id, null, ['status' => 'approved'], $note);

        return $po->refresh();
    }

    public function reject(PurchaseOrder $po, User $user, ?string $note = null): PurchaseOrder
    {
        if (! in_array($po->status, ['submitted', 'draft'], true)) {
            throw ValidationException::withMessages(['po' => 'PO ini tidak dapat ditolak.']);
        }

        $po->update([
            'status' => 'rejected',
            'notes' => trim(($po->notes ? $po->notes."\n" : '').($note ? 'Ditolak: '.$note : '')),
        ]);

        $this->audit->log('po.reject', 'purchase_order', $po->id, null, ['status' => 'rejected'], $note);

        return $po->refresh();
    }

    public function receive(PurchaseOrder $po, array $receivedQuantities, User $user, ?string $attachment = null): PurchaseOrder
    {
        if (! in_array($po->status, ['approved', 'received'], true)) {
            throw ValidationException::withMessages(['po' => 'PO belum disetujui atau sudah selesai.']);
        }

        if ($po->payment_method === 'cash' && (float) $po->total_amount < (float) setting('po_cash_receipt_threshold', 500000) && ! $attachment && ! $po->attachment) {
            throw ValidationException::withMessages(['attachment' => 'Foto nota wajib dilampirkan untuk pembelian tunai di bawah Rp 500.000.']);
        }

        return DB::transaction(function () use ($po, $receivedQuantities, $user, $attachment) {
            $po->load('items.product');
            $allReceived = true;

            foreach ($po->items as $item) {
                $qtyNow = min((float) ($receivedQuantities[$item->id] ?? 0), $item->remainingQty());
                if ($qtyNow <= 0) {
                    if ($item->remainingQty() > 0) {
                        $allReceived = false;
                    }

                    continue;
                }

                if ($item->item_type === 'product' && $item->product) {
                    $product = $item->product;
                    $isPurchaseUnit = $item->unit && $item->unit === $product->purchase_unit && $product->conversion_qty > 1;
                    $baseQty = (int) round($qtyNow * ($isPurchaseUnit ? $product->conversion_qty : 1));
                    $unitCost = $isPurchaseUnit ? ((float) $item->unit_price / $product->conversion_qty) : (float) $item->unit_price;

                    $this->inventory->move(
                        $product,
                        'in',
                        $baseQty,
                        $unitCost,
                        'po',
                        $po->id,
                        'penerimaan_po',
                        'Penerimaan '.$po->code,
                        $user
                    );

                    if ($po->payment_method === 'cash') {
                        $this->cash->recordCashOut(
                            $qtyNow * (float) $item->unit_price,
                            'pembelian',
                            'Penerimaan PO '.$po->code.' — '.$item->description,
                            'purchase_order',
                            $po->id,
                            $user,
                            $attachment ?: $po->attachment
                        );
                    }
                } elseif ($item->item_type === 'expense') {
                    $amount = $qtyNow * (float) $item->unit_price;

                    Expense::create([
                        'code' => $this->numbers->next('EXP', now()->format('Ymd'), 4),
                        'category' => $item->expense_category ?: 'lain_lain',
                        'description' => $item->description,
                        'amount' => $amount,
                        'expense_date' => now()->toDateString(),
                        'payment_method' => $po->payment_method,
                        'cash_session_id' => $po->payment_method === 'cash' ? $this->cash->activeSession($user)?->id : null,
                        'purchase_order_id' => $po->id,
                        'attachment' => $attachment ?: $po->attachment,
                        'created_by' => $user->id,
                    ]);

                    if ($po->payment_method === 'cash') {
                        $this->cash->recordCashOut(
                            $amount,
                            'pembelian',
                            'Penerimaan PO '.$po->code.' — '.$item->description,
                            'purchase_order',
                            $po->id,
                            $user,
                            $attachment ?: $po->attachment
                        );
                    }
                }

                $item->update(['received_qty' => (float) $item->received_qty + $qtyNow]);

                if ((float) $item->received_qty < (float) $item->qty) {
                    $allReceived = false;
                }
            }

            if ($allReceived) {
                $po->update(['status' => 'received', 'received_at' => now(), 'attachment' => $attachment ?: $po->attachment]);
            } else {
                $po->update(['status' => 'approved', 'attachment' => $attachment ?: $po->attachment]);
            }

            $this->audit->log('po.receive', 'purchase_order', $po->id, null, [
                'status' => $po->status,
                'all_received' => $allReceived,
            ]);

            return $po->refresh()->load('items.product');
        });
    }

    public function createExpense(array $data, User $user, ?string $attachment = null): Expense
    {
        return DB::transaction(function () use ($data, $user, $attachment) {
            $expense = Expense::create([
                'code' => $this->numbers->next('EXP', now()->format('Ymd'), 4),
                'category' => $data['category'],
                'description' => $data['description'],
                'amount' => $data['amount'],
                'expense_date' => $data['expense_date'] ?? now()->toDateString(),
                'payment_method' => $data['payment_method'] ?? 'cash',
                'cash_session_id' => ($data['payment_method'] ?? 'cash') === 'cash' ? $this->cash->activeSession($user)?->id : null,
                'attachment' => $attachment,
                'created_by' => $user->id,
            ]);

            if (($data['payment_method'] ?? 'cash') === 'cash') {
                $this->cash->recordCashOut(
                    (float) $data['amount'],
                    'kas_kecil',
                    $data['description'],
                    'expense',
                    $expense->id,
                    $user,
                    $attachment
                );
            }

            $this->audit->log('expense.create', 'expense', $expense->id, null, $expense->toArray());

            return $expense;
        });
    }

    public function createSupplier(array $data): Supplier
    {
        $supplier = Supplier::create($data);
        $this->audit->log('supplier.create', 'supplier', $supplier->id, null, $supplier->toArray());

        return $supplier;
    }

    public function createRecurringExpense(array $data): RecurringExpense
    {
        return RecurringExpense::create($data);
    }

    public function generateRecurringExpenses(): int
    {
        $generated = 0;

        foreach (RecurringExpense::query()->where('is_active', true)->get() as $template) {
            if ((int) $template->day_of_month !== (int) now()->day) {
                continue;
            }

            if ($template->last_generated && $template->last_generated->isSameMonth(now())) {
                continue;
            }

            $this->create([
                'supplier_name' => 'Biaya Rutin',
                'po_type' => 'expense',
                'request_date' => now()->toDateString(),
                'expected_date' => now()->toDateString(),
                'payment_method' => 'transfer',
                'priority' => 'normal',
                'notes' => 'Draft otomatis dari biaya rutin: '.$template->name,
                'items' => [[
                    'item_type' => 'expense',
                    'description' => $template->name,
                    'expense_category' => $template->category,
                    'qty' => 1,
                    'unit' => 'bulan',
                    'unit_price' => $template->amount,
                ]],
            ], User::query()->where('role', 'owner')->firstOrFail(), true);

            $template->update(['last_generated' => now()->toDateString()]);
            $generated++;
        }

        return $generated;
    }
}
