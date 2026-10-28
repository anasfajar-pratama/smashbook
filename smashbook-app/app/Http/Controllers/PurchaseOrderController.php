<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\RecurringExpense;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->input('tab', 'quick');
        $suppliers = Supplier::query()->orderBy('name')->get();
        $products = Product::query()->where('is_active', true)->orderBy('name')->get();
        $lowStock = Product::query()->lowStock()->orderBy('stock_qty')->get();

        $filterStatus = $request->input('status');
        $search = trim((string) $request->input('q'));

        $orders = PurchaseOrder::query()
            ->with(['supplier', 'requester', 'approver', 'items'])
            ->when($filterStatus, fn ($q) => $q->where('status', $filterStatus))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('code', 'like', '%'.$search.'%')
                        ->orWhere('supplier_name', 'like', '%'.$search.'%')
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $month = now();
        $expenses = Expense::query()
            ->with(['creator', 'purchaseOrder'])
            ->whereMonth('expense_date', $month->month)
            ->whereYear('expense_date', $month->year)
            ->latest('expense_date')
            ->get();

        $expenseByCategory = $expenses->groupBy('category')->map(fn ($rows) => (float) $rows->sum('amount'))->sortDesc();
        $recurring = RecurringExpense::query()->orderBy('day_of_month')->get();

        $stats = [
            'pending_count' => PurchaseOrder::query()->where('status', 'submitted')->count(),
            'pending_total' => (float) PurchaseOrder::query()->where('status', 'submitted')->sum('total_amount'),
            'incoming_count' => PurchaseOrder::query()->where('status', 'approved')->count(),
            'month_purchase' => (float) PurchaseOrder::query()->whereMonth('created_at', $month->month)->whereYear('created_at', $month->year)->sum('total_amount'),
            'month_expense' => (float) $expenses->sum('amount'),
        ];

        $supplierStats = $suppliers->map(function ($supplier) {
            $supplier->orders_count = $supplier->purchaseOrders()->count();
            $supplier->orders_total = (float) $supplier->purchaseOrders()->sum('total_amount');

            return $supplier;
        });

        $approvePo = $request->filled('approve')
            ? PurchaseOrder::query()->with(['items.product', 'supplier', 'requester'])->find($request->integer('approve'))
            : null;

        $receivePo = $request->filled('receive')
            ? PurchaseOrder::query()->with(['items.product', 'supplier', 'requester'])->find($request->integer('receive'))
            : null;

        return view('purchase-orders.index', compact(
            'tab', 'suppliers', 'products', 'lowStock', 'orders', 'filterStatus', 'search',
            'expenses', 'expenseByCategory', 'recurring', 'stats', 'supplierStats', 'approvePo', 'receivePo'
        ));
    }

    public function store(Request $request, PurchaseOrderService $service)
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier_name' => ['nullable', 'string', 'max:120'],
            'request_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'payment_method' => ['required', 'in:cash,transfer,tempo'],
            'priority' => ['required', 'in:normal,urgent'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'in:product,expense'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.expense_category' => ['nullable', 'string', 'max:60'],
            'items.*.description' => ['required', 'string', 'max:160'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $attachment = $request->file('attachment')?->store('receipts', 'public');
        if ($attachment) {
            $data['attachment'] = $attachment;
        }

        if (empty($data['supplier_name']) && ! empty($data['supplier_id'])) {
            $data['supplier_name'] = Supplier::find($data['supplier_id'])?->name;
        }

        $po = $service->create($data, $request->user(), $request->boolean('save_draft'));

        return redirect()->route('purchase-orders.index', ['tab' => 'list'])
            ->with('success', 'PO '.$po->code.' disimpan dengan status '.$po->status.'.');
    }

    public function approve(Request $request, PurchaseOrder $po, PurchaseOrderService $service)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $service->approve($po, $request->user(), $data['note'] ?? null);

        return redirect()->route('purchase-orders.index', ['tab' => 'list'])->with('success', 'PO '.$po->code.' disetujui.');
    }

    public function reject(Request $request, PurchaseOrder $po, PurchaseOrderService $service)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $service->reject($po, $request->user(), $data['note']);

        return redirect()->route('purchase-orders.index', ['tab' => 'list'])->with('success', 'PO '.$po->code.' ditolak.');
    }

    public function receive(Request $request, PurchaseOrder $po, PurchaseOrderService $service)
    {
        $data = $request->validate([
            'received' => ['required', 'array'],
            'received.*' => ['nullable', 'numeric', 'min:0'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $attachment = $request->file('attachment')?->store('receipts', 'public');
        $service->receive($po, $data['received'], $request->user(), $attachment);

        return redirect()->route('purchase-orders.index', ['tab' => 'list'])->with('success', 'Penerimaan PO '.$po->code.' dicatat.');
    }

    public function expenseStore(Request $request, PurchaseOrderService $service)
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:1'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,transfer,debit'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        if ($data['payment_method'] === 'cash' && (float) $data['amount'] < 500000 && ! $request->hasFile('attachment')) {
            throw ValidationException::withMessages(['attachment' => 'Nota wajib dilampirkan untuk pengeluaran tunai di bawah Rp 500.000.']);
        }

        $attachment = $request->file('attachment')?->store('receipts', 'public');
        $service->createExpense($data, $request->user(), $attachment);

        return redirect()->route('purchase-orders.index', ['tab' => 'opex'])->with('success', 'Biaya operasional dicatat.');
    }

    public function recurringStore(Request $request, PurchaseOrderService $service)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:1'],
            'day_of_month' => ['required', 'integer', 'between:1,28'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $service->createRecurringExpense($data + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('purchase-orders.index', ['tab' => 'opex'])->with('success', 'Biaya rutin ditambahkan.');
    }

    public function supplierStore(Request $request, PurchaseOrderService $service)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:300'],
            'category' => ['nullable', 'string', 'max:60'],
            'payment_term' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $service->createSupplier($data);

        return redirect()->route('purchase-orders.index', ['tab' => 'supplier'])->with('success', 'Supplier ditambahkan.');
    }
}
