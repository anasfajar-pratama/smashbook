<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\CashSession;
use App\Models\Court;
use App\Models\Expense;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class ReportService
{
    public function __construct(
        private PricingService $pricing,
        private InventoryService $inventory,
    ) {}

    public function dashboard(?Carbon $date = null): array
    {
        $date = ($date ?? now())->copy()->startOfDay();
        $bookingRevenue = (float) Booking::query()
            ->whereDate('booking_date', $date->toDateString())
            ->whereNotIn('status', ['cancelled', 'no_show', 'expired'])
            ->sum('total_amount');

        $posQuery = PosSale::query()->whereDate('created_at', $date->toDateString())->where('status', 'paid');
        $posRevenue = (float) (clone $posQuery)->sum('total_amount');
        $posCount = (clone $posQuery)->count();
        $posItems = (int) PosSaleItem::query()
            ->whereHas('sale', fn ($q) => $q->whereDate('created_at', $date->toDateString())->where('status', 'paid'))
            ->sum('qty');

        $activeCourts = Court::query()->where('is_active', true)->count();
        $operatingHours = count($this->pricing->operatingHours());
        $availableSlots = max($activeCourts * $operatingHours * 2, 1);
        $bookedSlots = BookingSlot::query()
            ->whereDate('slot_date', $date->toDateString())
            ->whereHas('booking', fn ($q) => $q->whereNotIn('status', ['cancelled', 'no_show', 'expired']))
            ->count();

        $cashDifference = (float) CashSession::query()
            ->whereDate('opened_at', $date->toDateString())
            ->where('status', 'closed')
            ->sum('difference');

        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $date->copy()->subDays($i);
            $series[] = [
                'date' => $day,
                'label' => $day->locale('id')->translatedFormat('D'),
                'booking' => (float) Booking::query()
                    ->whereDate('booking_date', $day->toDateString())
                    ->whereNotIn('status', ['cancelled', 'no_show', 'expired'])
                    ->sum('total_amount'),
                'pos' => (float) PosSale::query()
                    ->whereDate('created_at', $day->toDateString())
                    ->where('status', 'paid')
                    ->sum('total_amount'),
            ];
        }

        $todayBookings = Booking::query()
            ->with(['court', 'member'])
            ->whereDate('booking_date', $date->toDateString())
            ->orderBy('start_time')
            ->get();

        $lowStock = Product::query()->lowStock()->orderBy('stock_qty')->limit(5)->get();

        $pendingPos = PurchaseOrder::query()
            ->with('requester')
            ->whereIn('status', ['submitted', 'approved'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentAudit = AuditLog::query()->with('user')->latest('created_at')->limit(7)->get();

        $expiringMembers = Member::query()
            ->whereHas('subscriptions', function ($query) {
                $query->where('status', 'aktif')
                    ->whereDate('end_date', '>=', now()->toDateString())
                    ->whereDate('end_date', '<=', now()->addDays(7)->toDateString());
            })
            ->count();

        return [
            'date' => $date,
            'booking_revenue' => $bookingRevenue,
            'pos_revenue' => $posRevenue,
            'total_revenue' => $bookingRevenue + $posRevenue,
            'pos_count' => $posCount,
            'pos_items' => $posItems,
            'atv' => $posCount > 0 ? $posRevenue / $posCount : 0,
            'occupancy' => min(100, round(($bookedSlots / $availableSlots) * 100)),
            'booked_slots' => $bookedSlots,
            'available_slots' => $availableSlots,
            'cash_difference' => $cashDifference,
            'series' => $series,
            'today_bookings' => $todayBookings,
            'low_stock' => $lowStock,
            'low_stock_count' => Product::query()->lowStock()->count(),
            'pending_pos' => $pendingPos,
            'recent_audit' => $recentAudit,
            'expiring_members' => $expiringMembers,
            'active_session' => CashSession::query()->where('cashier_id', auth()->id())->where('status', 'open')->latest('opened_at')->first(),
        ];
    }

    public function revenueReport(Carbon $from, Carbon $to): array
    {
        $booking = Booking::query()
            ->whereBetween('booking_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', ['cancelled', 'no_show', 'expired'])
            ->selectRaw('SUM(total_amount) as total, COUNT(*) as count')
            ->first();

        $pos = PosSale::query()
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('status', 'paid')
            ->selectRaw('SUM(total_amount) as total, COUNT(*) as count')
            ->first();

        $membership = MemberSubscription::query()
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('SUM(price) as total, COUNT(*) as count')
            ->first();

        $bookingByDay = Booking::query()
            ->whereBetween('booking_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', ['cancelled', 'no_show', 'expired'])
            ->selectRaw('DATE(booking_date) as d, SUM(total_amount) as total')
            ->groupBy('d')->pluck('total', 'd')->all();

        $posByDay = PosSale::query()
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('status', 'paid')
            ->selectRaw('DATE(created_at) as d, SUM(total_amount) as total')
            ->groupBy('d')->pluck('total', 'd')->all();

        $membershipByDay = MemberSubscription::query()
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) as d, SUM(price) as total')
            ->groupBy('d')->pluck('total', 'd')->all();

        $days = [];
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $day) {
            $key = $day->toDateString();
            $days[] = [
                'date' => $day->copy(),
                'booking' => (float) ($bookingByDay[$key] ?? 0),
                'pos' => (float) ($posByDay[$key] ?? 0),
                'membership' => (float) ($membershipByDay[$key] ?? 0),
                'total' => (float) ($bookingByDay[$key] ?? 0) + (float) ($posByDay[$key] ?? 0) + (float) ($membershipByDay[$key] ?? 0),
            ];
        }

        return [
            'from' => $from,
            'to' => $to,
            'booking_total' => (float) ($booking->total ?? 0),
            'booking_count' => (int) ($booking->count ?? 0),
            'pos_total' => (float) ($pos->total ?? 0),
            'pos_count' => (int) ($pos->count ?? 0),
            'membership_total' => (float) ($membership->total ?? 0),
            'membership_count' => (int) ($membership->count ?? 0),
            'total' => (float) ($booking->total ?? 0) + (float) ($pos->total ?? 0) + (float) ($membership->total ?? 0),
            'days' => $days,
        ];
    }

    public function occupancyReport(Carbon $from, Carbon $to): array
    {
        $days = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $courts = Court::query()->where('is_active', true)->orderBy('sort_order')->get();
        $operatingHours = $this->pricing->operatingHours();
        $availableHours = max($courts->count() * count($operatingHours) * $days, 1);

        $bookedSlots = BookingSlot::query()
            ->whereBetween('slot_date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('booking', fn ($q) => $q->whereNotIn('status', ['cancelled', 'no_show', 'expired']))
            ->get();

        $bookedHours = $bookedSlots->count() * 0.5;
        $revenue = (float) $bookedSlots->sum('price');

        $perHour = [];
        foreach ($operatingHours as $hour) {
            $hourStart = $this->pricing->toMinutes($hour);
            $hourEnd = $hourStart + 60;
            $slots = $bookedSlots->filter(function ($slot) use ($hourStart, $hourEnd) {
                $m = $this->pricing->toMinutes($slot->slot_time);

                return $m >= $hourStart && $m < $hourEnd;
            });
            $perHour[] = [
                'hour' => $hour,
                'booked' => $slots->count(),
                'available' => $courts->count() * 2 * $days,
                'occupancy' => min(100, round($slots->count() / max($courts->count() * 2 * $days, 1) * 100)),
                'revenue' => (float) $slots->sum('price'),
            ];
        }

        $perCourt = $courts->map(function ($court) use ($bookedSlots, $days, $operatingHours) {
            $slots = $bookedSlots->where('court_id', $court->id);
            $available = count($operatingHours) * 2 * $days;

            return [
                'court' => $court,
                'booked' => $slots->count(),
                'hours' => $slots->count() * 0.5,
                'occupancy' => min(100, round($slots->count() / max($available, 1) * 100)),
                'revenue' => (float) $slots->sum('price'),
            ];
        });

        $sorted = collect($perHour)->sortBy('occupancy')->values();

        return [
            'from' => $from,
            'to' => $to,
            'occupancy' => min(100, round($bookedHours / $availableHours * 100)),
            'available_hours' => $availableHours,
            'booked_hours' => $bookedHours,
            'revenue_per_hour' => $bookedHours > 0 ? $revenue / $bookedHours : 0,
            'per_hour' => $perHour,
            'per_court' => $perCourt,
            'quietest' => $sorted->first(),
            'busiest' => $sorted->last(),
        ];
    }

    public function cashReport(Carbon $from, Carbon $to): array
    {
        $sessions = CashSession::query()
            ->with(['cashier', 'movements'])
            ->whereBetween('opened_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('opened_at')
            ->get();

        $cashIn = 0.0;
        $cashOut = 0.0;
        $difference = 0.0;
        $byCashier = [];

        foreach ($sessions as $session) {
            $in = (float) $session->movements->where('type', 'in')->where('category', '!=', 'modal_awal')->sum('amount');
            $out = (float) $session->movements->where('type', 'out')->sum('amount');
            $cashIn += $in;
            $cashOut += $out;
            $difference += (float) ($session->difference ?? 0);

            $key = $session->cashier_id;
            $byCashier[$key] ??= [
                'cashier' => $session->cashier,
                'shifts' => 0,
                'cash_in' => 0.0,
                'difference' => 0.0,
            ];
            $byCashier[$key]['shifts']++;
            $byCashier[$key]['cash_in'] += $in;
            $byCashier[$key]['difference'] += (float) ($session->difference ?? 0);
        }

        $nonCash = Payment::query()
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereIn('method', ['qris', 'transfer', 'debit'])
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->all();

        return [
            'from' => $from,
            'to' => $to,
            'sessions' => $sessions,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'non_cash' => (float) array_sum($nonCash),
            'non_cash_by_method' => $nonCash,
            'difference' => $difference,
            'by_cashier' => collect($byCashier)->values(),
        ];
    }

    public function stockReport(Carbon $from, Carbon $to): array
    {
        $category = PosSaleItem::query()
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->join('products', 'products.id', '=', 'pos_sale_items.product_id')
            ->where('pos_sales.status', 'paid')
            ->whereBetween('pos_sales.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('products.category as category, SUM(pos_sale_items.amount) as sales, SUM(pos_sale_items.cost_price_snapshot * pos_sale_items.qty) as cost, SUM(pos_sale_items.qty) as qty')
            ->groupBy('products.category')
            ->orderByDesc('sales')
            ->get()
            ->map(function ($row) {
                $row->profit = (float) $row->sales - (float) $row->cost;
                $row->margin = (float) $row->sales > 0 ? ($row->profit / (float) $row->sales) * 100 : 0;

                return $row;
            });

        $topProducts = PosSaleItem::query()
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->join('products', 'products.id', '=', 'pos_sale_items.product_id')
            ->where('pos_sales.status', 'paid')
            ->whereBetween('pos_sales.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('products.id, products.name, products.stock_qty, products.base_unit, SUM(pos_sale_items.qty) as qty, SUM(pos_sale_items.amount) as sales, SUM(pos_sale_items.amount - (pos_sale_items.cost_price_snapshot * pos_sale_items.qty)) as profit')
            ->groupBy('products.id', 'products.name', 'products.stock_qty', 'products.base_unit')
            ->orderByDesc('qty')
            ->limit(8)
            ->get();

        $posSales = (float) $category->sum('sales');
        $posCost = (float) $category->sum('cost');

        return [
            'from' => $from,
            'to' => $to,
            'stock_value' => $this->inventory->stockValue(),
            'purchases' => (float) PurchaseOrder::query()
                ->where('status', 'received')
                ->whereBetween('received_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->sum('total_amount'),
            'pos_sales' => $posSales,
            'pos_cost' => $posCost,
            'pos_profit' => $posSales - $posCost,
            'categories' => $category,
            'top_products' => $topProducts,
            'low_stock' => Product::query()->lowStock()->orderBy('stock_qty')->get(),
        ];
    }

    public function memberReport(Carbon $from, Carbon $to): array
    {
        $activeMembers = Member::query()
            ->whereHas('subscriptions', function ($q) {
                $q->where('status', 'aktif')->whereDate('end_date', '>=', now()->toDateString());
            })
            ->count();

        $newMembers = Member::query()->whereBetween('join_date', [$from->toDateString(), $to->toDateString()])->count();
        $churn = Member::query()
            ->whereHas('subscriptions', function ($q) use ($from, $to) {
                $q->where('status', 'aktif')->whereDate('end_date', '>=', $from->toDateString())->whereDate('end_date', '<=', $to->toDateString());
            })
            ->count();

        $membershipRevenue = (float) MemberSubscription::query()
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('price');

        $perType = MembershipType::query()->get()->map(function ($type) {
            return [
                'type' => $type,
                'members' => Member::query()->where('member_type_id', $type->id)->count(),
                'revenue' => (float) MemberSubscription::query()->where('membership_type_id', $type->id)->sum('price'),
                'renewals' => (int) MemberSubscription::query()->where('membership_type_id', $type->id)->count(),
            ];
        });

        $topMembers = Member::query()
            ->withCount(['bookings' => fn ($q) => $q->whereBetween('booking_date', [$from->toDateString(), $to->toDateString()])->whereNotIn('status', ['cancelled', 'no_show', 'expired'])])
            ->orderByDesc('bookings_count')
            ->limit(5)
            ->get()
            ->map(function ($member) use ($from, $to) {
                $member->spent = (float) PosSale::query()->where('member_id', $member->id)->where('status', 'paid')
                    ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->sum('total_amount');

                return $member;
            });

        $expiring = Member::query()
            ->with('activeSubscription')
            ->whereHas('subscriptions', function ($q) {
                $q->where('status', 'aktif')->whereDate('end_date', '>=', now()->toDateString())->whereDate('end_date', '<=', now()->addDays(7)->toDateString());
            })
            ->limit(10)->get();

        return [
            'from' => $from,
            'to' => $to,
            'active_members' => $activeMembers,
            'new_members' => $newMembers,
            'churn' => $churn,
            'membership_revenue' => $membershipRevenue,
            'per_type' => $perType,
            'top_members' => $topMembers,
            'expiring' => $expiring,
        ];
    }

    public function profitLoss(Carbon $from, Carbon $to): array
    {
        $revenue = $this->revenueReport($from, $to);
        $stock = $this->stockReport($from, $to);

        $expenses = Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('category, SUM(amount) as amount')
            ->groupBy('category')
            ->orderByDesc('amount')
            ->get();

        $totalExpenses = (float) $expenses->sum('amount');
        $gross = $revenue['total'] - $stock['pos_cost'];
        $net = $gross - $totalExpenses;

        return [
            'from' => $from,
            'to' => $to,
            'revenue' => $revenue,
            'hpp' => $stock['pos_cost'],
            'gross_profit' => $gross,
            'expenses' => $expenses,
            'total_expenses' => $totalExpenses,
            'net_profit' => $net,
            'gross_margin' => $revenue['total'] > 0 ? ($gross / $revenue['total']) * 100 : 0,
            'net_margin' => $revenue['total'] > 0 ? ($net / $revenue['total']) * 100 : 0,
            'stock' => $stock,
        ];
    }
}
