<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingRecurring;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\Deposit;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private PricingService $pricing,
        private DocumentNumberService $numbers,
        private CashService $cash,
        private MemberService $members,
        private PaymentService $payments,
        private AuditLogger $audit,
    ) {}

    public function quote(Court $court, Carbon $date, string $startTime, int $duration, ?Member $member = null): array
    {
        return $this->pricing->quote($court, $date, $startTime, $duration, $member);
    }

    public function create(array $data, User $user, bool $hold = false): Booking
    {
        $court = Court::findOrFail($data['court_id']);
        if (! $court->is_active) {
            throw ValidationException::withMessages(['court_id' => 'Lapangan ini sedang tidak aktif.']);
        }

        $date = Carbon::parse($data['booking_date'])->startOfDay();
        $member = ! empty($data['member_id']) ? Member::with('subscriptions.membershipType')->find($data['member_id']) : null;

        if ($member && in_array($member->status, ['blacklist', 'suspend'], true)) {
            throw ValidationException::withMessages(['member_id' => 'Member ini tidak diizinkan melakukan booking.']);
        }

        if ($date->lt(now()->startOfDay())) {
            throw ValidationException::withMessages(['booking_date' => 'Tanggal booking tidak boleh di masa lalu.']);
        }

        $advance = $member
            ? (int) setting('member_advance_days', 30)
            : (int) setting('public_advance_days', 14);

        if (now()->startOfDay()->diffInDays($date, false) > $advance) {
            throw ValidationException::withMessages([
                'booking_date' => 'Booking maksimal '.$advance.' hari ke depan untuk tipe penyewa ini.',
            ]);
        }

        $duration = (int) $data['duration_minutes'];
        $quote = $this->pricing->quote($court, $date, $data['start_time'], $duration, $member);
        $slotTimes = $this->pricing->slotTimes($data['start_time'], $duration);
        $expiresAt = $hold ? now()->addMinutes((int) setting('hold_minutes', 15)) : null;

        try {
            return DB::transaction(function () use ($data, $user, $court, $date, $member, $quote, $slotTimes, $hold, $expiresAt) {
                foreach ($slotTimes as $time) {
                    DB::table('court_slots')->insertOrIgnore([
                        'court_id' => $court->id,
                        'slot_date' => $date->toDateString(),
                        'slot_time' => $time,
                        'status' => 'open',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $locked = CourtSlot::query()
                    ->where('court_id', $court->id)
                    ->whereDate('slot_date', $date->toDateString())
                    ->where(function ($query) use ($slotTimes) {
                        foreach ($slotTimes as $time) {
                            $query->orWhere('slot_time', $time);
                        }
                    })
                    ->orderBy('slot_time')
                    ->lockForUpdate()
                    ->get();

                foreach ($locked as $slot) {
                    if (! $slot->booking_id) {
                        continue;
                    }

                    if ($slot->status === 'held' && $slot->expires_at && $slot->expires_at->isPast()) {
                        $this->releaseBookingSlots($slot->booking_id, true);

                        continue;
                    }

                    throw ValidationException::withMessages([
                        'slot' => 'Slot '.jam($slot->slot_time).' di '.$court->name.' baru saja diambil operator lain. Silakan pilih jam lain.',
                    ]);
                }

                $booking = Booking::create([
                    'code' => $this->numbers->next('BK', now()->format('Ymd'), 4),
                    'member_id' => $member?->id,
                    'customer_name' => $data['customer_name'],
                    'customer_phone' => $data['customer_phone'] ?? null,
                    'court_id' => $court->id,
                    'booking_date' => $date->toDateString(),
                    'start_time' => $quote['start_time'],
                    'end_time' => $quote['end_time'],
                    'duration_minutes' => $quote['duration_minutes'],
                    'base_amount' => $quote['base_amount'],
                    'discount_amount' => $quote['discount_amount'],
                    'total_amount' => $quote['total_amount'],
                    'paid_amount' => 0,
                    'payment_status' => 'unpaid',
                    'status' => $hold ? 'held' : 'confirmed',
                    'source' => $data['source'] ?? 'walkin',
                    'created_by' => $user->id,
                    'expires_at' => $expiresAt,
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($quote['slots'] as $slot) {
                    BookingSlot::create([
                        'booking_id' => $booking->id,
                        'court_id' => $court->id,
                        'slot_date' => $date->toDateString(),
                        'slot_time' => $slot['time'],
                        'price' => $slot['price'],
                        'category' => $slot['category'],
                    ]);
                }

                CourtSlot::query()
                    ->where('court_id', $court->id)
                    ->whereDate('slot_date', $date->toDateString())
                    ->where(function ($query) use ($slotTimes) {
                        foreach ($slotTimes as $time) {
                            $query->orWhere('slot_time', $time);
                        }
                    })
                    ->update([
                        'booking_id' => $booking->id,
                        'status' => $hold ? 'held' : 'confirmed',
                        'expires_at' => $expiresAt,
                        'updated_at' => now(),
                    ]);

                if (! $hold && ! empty($data['payment_amount']) && (float) $data['payment_amount'] > 0) {
                    $this->applyPayment($booking, $member, $user, $data);
                }

                $this->audit->log('booking.create', 'booking', $booking->id, null, $booking->fresh()->toArray());

                return $booking->fresh(['court', 'member', 'slots']);
            });
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'duplicate')) {
                throw ValidationException::withMessages([
                    'slot' => 'Slot baru saja diambil operator lain. Silakan pilih jam lain.',
                ]);
            }

            throw $e;
        }
    }

    private function applyPayment(Booking $booking, ?Member $member, User $user, array $data): void
    {
        $amount = min((float) $data['payment_amount'], (float) $booking->total_amount);
        $method = $data['payment_method'] ?? 'cash';

        if ($method === 'deposit') {
            if (! $member) {
                throw ValidationException::withMessages(['payment_method' => 'Pembayaran deposit memerlukan member.']);
            }

            $this->members->useDeposit($member, $amount, 'booking', $booking->id, $user);
            $this->payments->record('booking', $booking->id, 'deposit', $amount, $user, null, null, 'Pembayaran booking dengan deposit');
        } else {
            $this->payments->record('booking', $booking->id, $method, $amount, $user, $data['payment_reference'] ?? null, null, 'Pembayaran booking '.$booking->code);
        }

        $paid = (float) $booking->paid_amount + $amount;
        $status = $paid >= (float) $booking->total_amount ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

        $booking->update(['paid_amount' => $paid, 'payment_status' => $status]);
    }

    public function addPayment(Booking $booking, string $method, float $amount, User $user, ?string $reference = null): Payment
    {
        if (in_array($booking->status, ['cancelled', 'no_show', 'expired'], true)) {
            throw ValidationException::withMessages(['booking' => 'Booking ini tidak dapat dibayar.']);
        }

        if ($booking->status === 'held' && $booking->expires_at?->isPast()) {
            $booking->update(['status' => 'expired']);
            $this->releaseBookingSlots($booking->id);

            throw ValidationException::withMessages([
                'booking' => 'Masa hold booking ini sudah habis. Silakan buat booking baru.',
            ]);
        }

        $remaining = (float) $booking->total_amount - (float) $booking->paid_amount;
        if ($amount <= 0 || $amount > $remaining) {
            throw ValidationException::withMessages(['amount' => 'Nominal pembayaran tidak valid.']);
        }

        return DB::transaction(function () use ($booking, $method, $amount, $user, $reference) {
            if ($booking->status === 'held') {
                $this->confirmHold($booking);
            }

            if ($method === 'deposit') {
                if (! $booking->member) {
                    throw ValidationException::withMessages(['payment_method' => 'Pembayaran deposit memerlukan member.']);
                }
                $this->members->useDeposit($booking->member, $amount, 'booking', $booking->id, $user);
                $payment = $this->payments->record('booking', $booking->id, 'deposit', $amount, $user, null, null, 'Pembayaran booking dengan deposit');
            } else {
                $payment = $this->payments->record('booking', $booking->id, $method, $amount, $user, $reference, null, 'Pembayaran booking '.$booking->code);
            }

            $paid = (float) $booking->paid_amount + $amount;
            $booking->update([
                'paid_amount' => $paid,
                'payment_status' => $paid >= (float) $booking->total_amount ? 'paid' : 'partial',
            ]);

            $this->audit->log('booking.payment', 'booking', $booking->id, null, ['amount' => $amount, 'method' => $method]);

            return $payment;
        });
    }

    public function confirmHold(Booking $booking): Booking
    {
        if ($booking->status !== 'held') {
            throw ValidationException::withMessages(['booking' => 'Hanya booking berstatus hold yang dapat dikonfirmasi.']);
        }

        if ($booking->expires_at && $booking->expires_at->isPast()) {
            $booking->update(['status' => 'expired']);
            $this->releaseBookingSlots($booking->id);

            throw ValidationException::withMessages([
                'booking' => 'Masa hold booking ini sudah habis. Silakan buat booking baru.',
            ]);
        }

        return DB::transaction(function () use ($booking) {
            $booking->update(['status' => 'confirmed', 'expires_at' => null]);

            CourtSlot::query()
                ->where('booking_id', $booking->id)
                ->update([
                    'status' => 'confirmed',
                    'expires_at' => null,
                    'updated_at' => now(),
                ]);

            $this->audit->log('booking.confirm', 'booking', $booking->id, ['status' => 'held'], ['status' => 'confirmed']);

            return $booking->fresh();
        });
    }

    public function cancel(Booking $booking, string $reason, User $user): BookingCancellation
    {
        if (in_array($booking->status, ['cancelled', 'completed', 'no_show', 'expired'], true)) {
            throw ValidationException::withMessages(['booking' => 'Booking ini tidak dapat dibatalkan.']);
        }

        return DB::transaction(function () use ($booking, $reason, $user) {
            $start = Carbon::parse($booking->booking_date->toDateString().' '.$booking->start_time);
            $hours = now()->diffInMinutes($start, false) / 60;

            $pct = $hours >= 24 ? 100 : ($hours >= 6 ? 50 : 0);
            $refund = round((float) $booking->paid_amount * ($pct / 100), 2);

            if ($refund > 0) {
                $cashPaid = (float) Payment::query()
                    ->where('ref_type', 'booking')
                    ->where('ref_id', $booking->id)
                    ->where('method', 'cash')
                    ->sum('amount');

                $cashRefund = min($refund, $cashPaid);
                if ($cashRefund > 0) {
                    $this->cash->recordCashOut(
                        $cashRefund,
                        'refund',
                        'Refund pembatalan '.$booking->code,
                        'booking_cancellation',
                        $booking->id,
                        $user
                    );
                }

                if ($booking->member) {
                    $depositPaid = (float) Payment::query()
                        ->where('ref_type', 'booking')
                        ->where('ref_id', $booking->id)
                        ->where('method', 'deposit')
                        ->sum('amount');
                    $depositRefund = min($refund - $cashRefund, $depositPaid);

                    if ($depositRefund > 0) {
                        $member = Member::query()->lockForUpdate()->find($booking->member_id);
                        $newBalance = (float) $member->deposit_balance + $depositRefund;
                        Deposit::create([
                            'code' => $this->numbers->next('DEP', now()->format('Ymd'), 4),
                            'member_id' => $member->id,
                            'type' => 'refund',
                            'amount' => $depositRefund,
                            'balance_after' => $newBalance,
                            'ref_type' => 'booking_cancellation',
                            'ref_id' => $booking->id,
                            'created_by' => $user->id,
                        ]);
                        $member->update(['deposit_balance' => $newBalance]);
                    }
                }
            }

            $cancellation = BookingCancellation::create([
                'booking_id' => $booking->id,
                'cancelled_at' => now(),
                'reason' => $reason,
                'refund_amount' => $refund,
                'refund_pct' => $pct,
                'approved_by' => $user->id,
            ]);

            $booking->update(['status' => 'cancelled']);
            $this->releaseBookingSlots($booking->id);

            $this->audit->log('booking.cancel', 'booking', $booking->id, ['status' => $booking->getOriginal('status')], [
                'status' => 'cancelled',
                'refund_amount' => $refund,
                'refund_pct' => $pct,
            ], $reason);

            return $cancellation;
        });
    }

    public function checkIn(Booking $booking): Booking
    {
        if (! in_array($booking->status, ['confirmed'], true)) {
            throw ValidationException::withMessages(['booking' => 'Hanya booking terkonfirmasi yang dapat check-in.']);
        }

        $booking->update(['status' => 'checked_in', 'checked_in_at' => now()]);
        $this->audit->log('booking.checkin', 'booking', $booking->id, null, ['status' => 'checked_in']);

        return $booking;
    }

    public function checkOut(Booking $booking): Booking
    {
        if (! in_array($booking->status, ['checked_in'], true)) {
            throw ValidationException::withMessages(['booking' => 'Booking belum check-in.']);
        }

        $booking->update(['status' => 'completed', 'checked_out_at' => now()]);
        $this->audit->log('booking.checkout', 'booking', $booking->id, null, ['status' => 'completed']);

        return $booking;
    }

    public function noShow(Booking $booking): Booking
    {
        if (! in_array($booking->status, ['confirmed', 'held'], true)) {
            throw ValidationException::withMessages(['booking' => 'Status booking tidak dapat ditandai no-show.']);
        }

        return DB::transaction(function () use ($booking) {
            $booking->update(['status' => 'no_show']);
            $this->releaseBookingSlots($booking->id);

            if ($booking->member_id) {
                $member = Member::query()->lockForUpdate()->find($booking->member_id);
                $count = Booking::query()
                    ->where('member_id', $member->id)
                    ->where('status', 'no_show')
                    ->whereDate('booking_date', '>=', now()->subDays(30)->toDateString())
                    ->count();

                $member->update([
                    'no_show_count' => $count,
                    'status' => $count >= 3 && ! in_array($member->status, ['blacklist', 'suspend'], true)
                        ? 'perlu_konfirmasi'
                        : $member->status,
                ]);
            }

            $this->audit->log('booking.noshow', 'booking', $booking->id, null, ['status' => 'no_show']);

            return $booking;
        });
    }

    public function releaseExpiredHolds(): int
    {
        $expired = Booking::query()
            ->where('status', 'held')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $booking) {
            $booking->update(['status' => 'expired']);
            $this->releaseBookingSlots($booking->id);
        }

        return $expired->count();
    }

    public function releaseBookingSlots(int $bookingId, bool $onlyExpiredBooking = false): void
    {
        if ($onlyExpiredBooking) {
            $booking = Booking::find($bookingId);
            if ($booking && $booking->status === 'held' && $booking->expires_at?->isPast()) {
                $booking->update(['status' => 'expired']);
            }
        }

        CourtSlot::query()
            ->where('booking_id', $bookingId)
            ->update([
                'booking_id' => null,
                'status' => 'open',
                'expires_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function createRecurring(array $data, User $user): BookingRecurring
    {
        return BookingRecurring::create([
            'member_id' => $data['member_id'] ?? null,
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'court_id' => $data['court_id'],
            'day_of_week' => (int) $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
            'status' => 'aktif',
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
        ]);
    }

    public function stopRecurring(BookingRecurring $recurring): void
    {
        $recurring->update(['status' => 'dihentikan']);
        $this->audit->log('booking.recurring.stop', 'booking_recurring', $recurring->id, null, ['status' => 'dihentikan']);
    }

    public function generateRecurringBookings(): int
    {
        $created = 0;

        foreach (BookingRecurring::query()->where('status', 'aktif')->get() as $recurring) {
            $from = max(now()->startOfDay(), $recurring->valid_from->copy()->startOfDay());
            $limitDays = $recurring->member_id ? (int) setting('member_advance_days', 30) : (int) setting('public_advance_days', 14);
            $until = min(now()->startOfDay()->addDays($limitDays), $recurring->valid_until->copy()->endOfDay());
            $cursor = $from->copy();

            while ($cursor <= $until) {
                if ((int) $cursor->dayOfWeek === (int) $recurring->day_of_week) {
                    $exists = Booking::query()
                        ->where('court_id', $recurring->court_id)
                        ->whereDate('booking_date', $cursor->toDateString())
                        ->where('start_time', $recurring->start_time)
                        ->whereNotIn('status', ['cancelled', 'no_show', 'expired'])
                        ->exists();

                    if (! $exists) {
                        $duration = $this->pricing->toMinutes($recurring->end_time) - $this->pricing->toMinutes($recurring->start_time);

                        try {
                            $this->create([
                                'member_id' => $recurring->member_id,
                                'customer_name' => $recurring->customer_name,
                                'customer_phone' => $recurring->customer_phone,
                                'court_id' => $recurring->court_id,
                                'booking_date' => $cursor->toDateString(),
                                'start_time' => $recurring->start_time,
                                'duration_minutes' => $duration,
                                'source' => 'wa',
                                'notes' => 'Booking rutin otomatis',
                            ], auth()->user() ?? User::query()->where('role', 'owner')->firstOrFail());
                            $created++;
                        } catch (ValidationException $e) {
                            // Slot sudah terisi — lewati tanggal ini.
                        }
                    }
                }

                $cursor->addDay();
            }
        }

        return $created;
    }
}
