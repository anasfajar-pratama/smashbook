<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Member;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Services\CashService;
use App\Services\PosService;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function index(Request $request, CashService $cash)
    {
        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $categories = $products->pluck('category')->unique()->values();
        $members = Member::query()->orderBy('name')->get();
        $bookings = Booking::query()
            ->with('court')
            ->whereDate('booking_date', now()->toDateString())
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->orderBy('start_time')
            ->get();

        $session = $cash->activeSession($request->user());
        $shiftQuery = PosSale::query()
            ->whereDate('created_at', now()->toDateString())
            ->where('cashier_id', $request->user()->id)
            ->where('status', 'paid');

        $shiftStats = [
            'count' => (clone $shiftQuery)->count(),
            'total' => (float) (clone $shiftQuery)->sum('total_amount'),
            'items' => (int) PosSaleItem::query()
                ->whereHas('sale', fn ($q) => $q->whereDate('created_at', now()->toDateString())->where('cashier_id', $request->user()->id)->where('status', 'paid'))
                ->sum('qty'),
        ];
        $shiftStats['atv'] = $shiftStats['count'] > 0 ? $shiftStats['total'] / $shiftStats['count'] : 0;

        $recentSales = PosSale::query()
            ->with(['items', 'payments', 'member'])
            ->whereDate('created_at', now()->toDateString())
            ->where('cashier_id', $request->user()->id)
            ->latest()
            ->limit(5)
            ->get();

        $receipt = $request->filled('receipt')
            ? PosSale::query()->with(['items.product', 'payments', 'member', 'cashier', 'cashSession'])->find($request->integer('receipt'))
            : null;

        $lowStockCount = Product::query()->lowStock()->count();

        return view('pos.index', compact(
            'products', 'categories', 'members', 'bookings', 'session', 'shiftStats',
            'recentSales', 'receipt', 'lowStockCount'
        ));
    }

    public function checkout(Request $request, PosService $pos)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
            'member_id' => ['nullable', 'exists:members,id'],
            'booking_id' => ['nullable', 'exists:bookings,id'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'owner_pin' => ['nullable', 'string', 'max:10'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'qris' => ['nullable', 'numeric', 'min:0'],
            'transfer' => ['nullable', 'numeric', 'min:0'],
            'debit' => ['nullable', 'numeric', 'min:0'],
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $member = ! empty($data['member_id']) ? Member::with('subscriptions.membershipType')->find($data['member_id']) : null;

        $result = $pos->checkout(
            $request->user(),
            $data['items'],
            $member,
            [
                'cash_received' => $data['cash_received'] ?? 0,
                'qris' => $data['qris'] ?? 0,
                'transfer' => $data['transfer'] ?? 0,
                'debit' => $data['debit'] ?? 0,
                'deposit' => $data['deposit'] ?? 0,
                'reference_no' => $data['reference_no'] ?? null,
            ],
            (float) ($data['discount_amount'] ?? 0),
            $data['discount_reason'] ?? null,
            $data['owner_pin'] ?? null,
            ! empty($data['booking_id']) ? (int) $data['booking_id'] : null,
            $data['notes'] ?? null
        );

        return redirect()
            ->route('pos.index', ['receipt' => $result['sale']->id])
            ->with('success', 'Transaksi '.$result['sale']->code.' berhasil. Kembalian: '.rupiah($result['change']).'.')
            ->with('change', $result['change']);
    }

    public function receipt(PosSale $sale)
    {
        $sale->load(['items.product', 'payments', 'member', 'cashier', 'cashSession', 'booking']);

        return view('pos.receipt', compact('sale'));
    }

    public function history(Request $request)
    {
        $date = $request->input('date', now()->toDateString());
        $status = $request->input('status');
        $search = trim((string) $request->input('q'));

        $sales = PosSale::query()
            ->with(['member', 'cashier', 'payments'])
            ->when($date, fn ($q) => $q->whereDate('created_at', $date))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('code', 'like', '%'.$search.'%')->orWhereHas('member', fn ($m) => $m->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('pos.history', compact('sales', 'date', 'status', 'search'));
    }

    public function void(Request $request, PosSale $sale, PosService $pos)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'owner_pin' => ['required', 'string', 'max:10'],
            'restore_stock' => ['nullable', 'boolean'],
        ]);

        $pos->void($sale, $request->user(), $data['reason'], $data['owner_pin'], $request->boolean('restore_stock', true));

        return back()->with('success', 'Transaksi '.$sale->code.' berhasil di-void.');
    }
}
