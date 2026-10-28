<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingRecurring;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\Member;
use App\Services\BookingService;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function index(Request $request, PricingService $pricing, BookingService $bookingService)
    {
        $bookingService->releaseExpiredHolds();

        $date = Carbon::parse($request->input('date', now()->toDateString()))->startOfDay();
        $courts = Court::query()->orderBy('sort_order')->get();
        $courtFilter = $request->integer('court') ?: null;
        $statusFilter = $request->input('status');
        $tab = $request->input('tab', 'board');

        $bookings = Booking::query()
            ->with(['court', 'member', 'cancellation'])
            ->whereDate('booking_date', $date->toDateString())
            ->when($courtFilter, fn ($q) => $q->where('court_id', $courtFilter))
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->orderBy('start_time')
            ->get();

        $boardCourts = $courts->when($courtFilter, fn ($collection) => $collection->where('id', $courtFilter));
        $hours = $pricing->operatingHours();
        $boardBookings = $bookings->whereNotIn('status', ['cancelled', 'expired']);
        $board = [];

        foreach ($hours as $hour) {
            $hourMinutes = $pricing->toMinutes($hour);
            $row = ['hour' => $hour, 'peak' => $hourMinutes >= 16 * 60 && $hourMinutes < 22 * 60, 'cells' => []];

            foreach ($boardCourts as $court) {
                $booking = $boardBookings->first(function ($candidate) use ($court, $hourMinutes, $pricing) {
                    return $candidate->court_id === $court->id
                        && $hourMinutes >= $pricing->toMinutes($candidate->start_time)
                        && $hourMinutes < $pricing->toMinutes($candidate->end_time);
                });

                $row['cells'][] = ['court' => $court, 'booking' => $booking];
            }

            $board[] = $row;
        }

        $activeBookings = $bookings->whereNotIn('status', ['cancelled', 'expired', 'no_show']);
        $availableSlots = max($boardCourts->where('is_active', true)->count() * count($hours) * 2, 1);
        $bookedSlots = BookingSlot::query()
            ->whereDate('slot_date', $date->toDateString())
            ->whereIn('booking_id', $activeBookings->pluck('id'))
            ->count();

        $stats = [
            'occupancy' => min(100, round(($bookedSlots / $availableSlots) * 100)),
            'confirmed_value' => (float) $activeBookings->sum('total_amount'),
            'unpaid_value' => (float) $activeBookings->sum(fn ($b) => max((float) $b->total_amount - (float) $b->paid_amount, 0)),
            'hold_count' => $bookings->where('status', 'held')->count(),
        ];

        $payBooking = $request->filled('pay') ? Booking::with('member')->find($request->integer('pay')) : null;
        $cancelBooking = $request->filled('cancel') ? Booking::find($request->integer('cancel')) : null;

        $recurring = BookingRecurring::query()->with(['court', 'member'])->latest()->get();
        $members = Member::query()->active()->orderBy('name')->get();

        return view('bookings.index', compact(
            'date', 'courts', 'bookings', 'board', 'hours', 'stats', 'tab',
            'courtFilter', 'statusFilter', 'payBooking', 'cancelBooking', 'recurring', 'members', 'pricing'
        ));
    }

    public function quote(Request $request, BookingService $bookings)
    {
        $data = $request->validate([
            'court_id' => ['required', 'exists:courts,id'],
            'booking_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:30', 'max:480', 'multiple_of:30'],
            'member_id' => ['nullable', 'exists:members,id'],
        ]);

        $member = ! empty($data['member_id'])
            ? Member::with('subscriptions.membershipType')->find($data['member_id'])
            : null;

        $quote = $bookings->quote(
            Court::findOrFail($data['court_id']),
            Carbon::parse($data['booking_date']),
            $data['start_time'],
            (int) $data['duration_minutes'],
            $member
        );

        $quote['hold_minutes'] = (int) setting('hold_minutes', 15);
        $quote['dp_percent'] = (int) setting('dp_percent', 50);

        return response()->json($quote);
    }

    public function store(Request $request, BookingService $bookings)
    {
        $data = $request->validate([
            'member_id' => ['nullable', 'exists:members,id'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'court_id' => ['required', 'exists:courts,id'],
            'booking_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:30', 'max:480', 'multiple_of:30'],
            'source' => ['required', 'in:telepon,wa,walkin'],
            'notes' => ['nullable', 'string', 'max:500'],
            'hold' => ['nullable', 'boolean'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:cash,qris,transfer,debit,deposit'],
            'payment_reference' => ['nullable', 'string', 'max:80'],
            'pay_at_venue' => ['nullable', 'boolean'],
        ]);

        if ($request->user()->isOperator()) {
            if ($data['booking_date'] !== now()->toDateString()) {
                throw ValidationException::withMessages([
                    'booking_date' => 'Operator hanya dapat membuat booking walk-in untuk hari ini.',
                ]);
            }

            $data['source'] = 'walkin';
            $data['payment_amount'] = 0;
        }

        $hold = $request->boolean('hold');
        if ($hold) {
            $data['payment_amount'] = 0;
        } elseif (! $request->user()->isOperator()
            && ! $request->boolean('pay_at_venue')
            && (float) ($data['payment_amount'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'payment_amount' => 'Isi nominal pembayaran, pilih "Bayar di tempat", atau simpan sebagai hold.',
            ]);
        }

        $booking = $bookings->create($data, $request->user(), $hold);

        return redirect()
            ->route('bookings.index', ['date' => $booking->booking_date->toDateString()])
            ->with('success', ($hold ? 'Hold booking ' : 'Booking ').$booking->code.' berhasil dibuat.');
    }

    public function payment(Request $request, Booking $booking, BookingService $bookings)
    {
        $data = $request->validate([
            'method' => ['required', 'in:cash,qris,transfer,debit,deposit'],
            'amount' => ['required', 'numeric', 'min:1'],
            'reference_no' => ['nullable', 'string', 'max:80'],
        ]);

        $bookings->addPayment($booking, $data['method'], (float) $data['amount'], $request->user(), $data['reference_no'] ?? null);

        return back()->with('success', 'Pembayaran booking '.$booking->code.' berhasil dicatat.');
    }

    public function confirm(Booking $booking, BookingService $bookings)
    {
        $bookings->confirmHold($booking);

        return back()->with('success', 'Booking '.$booking->code.' berhasil dikonfirmasi.');
    }

    public function cancel(Request $request, Booking $booking, BookingService $bookings)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $cancellation = $bookings->cancel($booking, $data['reason'], $request->user());

        return back()->with('success', 'Booking dibatalkan. Refund: '.rupiah($cancellation->refund_amount).' ('.(int) $cancellation->refund_pct.'%).');
    }

    public function checkIn(Booking $booking, BookingService $bookings)
    {
        $bookings->checkIn($booking);

        return back()->with('success', 'Check-in '.$booking->code.' berhasil.');
    }

    public function checkOut(Booking $booking, BookingService $bookings)
    {
        $bookings->checkOut($booking);

        return back()->with('success', 'Check-out '.$booking->code.' selesai.');
    }

    public function noShow(Booking $booking, BookingService $bookings)
    {
        $bookings->noShow($booking);

        return back()->with('success', 'Booking '.$booking->code.' ditandai no-show.');
    }

    public function recurringStore(Request $request, BookingService $bookings)
    {
        $data = $request->validate([
            'member_id' => ['nullable', 'exists:members,id'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'court_id' => ['required', 'exists:courts,id'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $bookings->createRecurring($data, $request->user());

        return redirect()->route('bookings.index', ['tab' => 'recurring'])->with('success', 'Jadwal rutin berhasil dibuat.');
    }

    public function recurringStop(BookingRecurring $recurring, BookingService $bookings)
    {
        $bookings->stopRecurring($recurring);

        return redirect()->route('bookings.index', ['tab' => 'recurring'])->with('success', 'Jadwal rutin dihentikan.');
    }

    public function proof(Booking $booking)
    {
        $booking->load(['court', 'member', 'slots', 'payments']);

        return view('bookings.proof', compact('booking'));
    }
}
