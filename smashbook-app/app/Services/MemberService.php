<?php

namespace App\Services;

use App\Models\Deposit;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\MemberSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private PaymentService $payments,
        private AuditLogger $audit,
    ) {}

    public function create(array $data, User $user): Member
    {
        if (Member::query()->where('phone', $data['phone'])->exists()) {
            throw ValidationException::withMessages(['phone' => 'Nomor HP sudah terdaftar sebagai member.']);
        }

        return DB::transaction(function () use ($data, $user) {
            $member = Member::create([
                'code' => $this->numbers->next('MBR', null, 5),
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'gender' => $data['gender'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'address' => $data['address'] ?? null,
                'member_type_id' => $data['member_type_id'] ?? null,
                'join_date' => $data['join_date'] ?? now()->toDateString(),
                'status' => 'aktif',
                'deposit_balance' => 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $start = Carbon::parse($data['start_date'] ?? now()->toDateString());
            $type = ! empty($data['member_type_id'])
                ? MembershipType::find($data['member_type_id'])
                : null;

            if ($type) {
                $this->createSubscription($member, $type, $start, $data['payment_method'] ?? 'cash', $user, $data['reference_no'] ?? null);
                $member->update(['member_type_id' => $type->id]);
            }

            $initialDeposit = (float) ($data['initial_deposit'] ?? 0);
            if ($initialDeposit > 0) {
                $this->topup($member, $initialDeposit, $data['deposit_method'] ?? ($data['payment_method'] ?? 'cash'), $data['reference_no'] ?? null, $user);
            }

            $this->audit->log('member.create', 'member', $member->id, null, $member->toArray());

            return $member->refresh();
        });
    }

    public function renew(Member $member, MembershipType $type, ?Carbon $startDate, string $paymentMethod, ?string $reference, User $user): MemberSubscription
    {
        return DB::transaction(function () use ($member, $type, $startDate, $paymentMethod, $reference, $user) {
            $activeEnd = $member->subscriptions()
                ->where('status', 'aktif')
                ->whereDate('end_date', '>=', now()->toDateString())
                ->max('end_date');

            if ($activeEnd) {
                $start = Carbon::parse($activeEnd)->addDay();
            } else {
                $start = $startDate ?: now();
            }

            $subscription = $this->createSubscription($member, $type, $start, $paymentMethod, $user, $reference);

            $member->update([
                'member_type_id' => $type->id,
                'status' => 'aktif',
            ]);

            $this->audit->log('member.renew', 'member', $member->id, null, [
                'membership_type_id' => $type->id,
                'start_date' => $subscription->start_date->toDateString(),
                'end_date' => $subscription->end_date->toDateString(),
            ]);

            return $subscription;
        });
    }

    private function createSubscription(Member $member, MembershipType $type, Carbon $start, string $paymentMethod, User $user, ?string $reference): MemberSubscription
    {
        $end = $start->copy()->addDays($type->duration_days);
        $invoice = $this->numbers->next('SUB', now()->format('Ymd'), 4);

        $subscription = MemberSubscription::create([
            'member_id' => $member->id,
            'membership_type_id' => $type->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'price' => $type->price,
            'paid_amount' => $type->price,
            'status' => 'aktif',
            'invoice_code' => $invoice,
            'created_by' => $user->id,
        ]);

        if ((float) $type->price > 0) {
            $this->payments->record('subscription', $subscription->id, $paymentMethod, (float) $type->price, $user, $reference, null, 'Pembayaran paket membership');
        }

        return $subscription;
    }

    public function topup(Member $member, float $amount, string $method, ?string $reference, User $user): Deposit
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nominal top-up harus lebih dari nol.']);
        }

        return DB::transaction(function () use ($member, $amount, $method, $reference, $user) {
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);
            $newBalance = (float) $member->deposit_balance + $amount;

            $deposit = Deposit::create([
                'code' => $this->numbers->next('DEP', now()->format('Ymd'), 4),
                'member_id' => $member->id,
                'type' => 'topup',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'ref_type' => null,
                'ref_id' => null,
                'created_by' => $user->id,
            ]);

            $member->update(['deposit_balance' => $newBalance]);

            $this->payments->record('deposit', $deposit->id, $method, $amount, $user, $reference, null, 'Top-up deposit member');

            $this->audit->log('deposit.topup', 'member', $member->id, ['deposit_balance' => $newBalance - $amount], ['deposit_balance' => $newBalance]);

            return $deposit;
        });
    }

    public function useDeposit(Member $member, float $amount, string $refType, int $refId, User $user): Deposit
    {
        return DB::transaction(function () use ($member, $amount, $refType, $refId, $user) {
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);

            if ((float) $member->deposit_balance < $amount) {
                throw ValidationException::withMessages(['deposit' => 'Saldo deposit member tidak mencukupi.']);
            }

            $newBalance = (float) $member->deposit_balance - $amount;

            $deposit = Deposit::create([
                'code' => $this->numbers->next('DEP', now()->format('Ymd'), 4),
                'member_id' => $member->id,
                'type' => 'usage',
                'amount' => -$amount,
                'balance_after' => $newBalance,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'created_by' => $user->id,
            ]);

            $member->update(['deposit_balance' => $newBalance]);

            return $deposit;
        });
    }

    public function resetNoShow(Member $member): void
    {
        $member->update(['no_show_count' => 0, 'status' => $member->status === 'perlu_konfirmasi' ? 'aktif' : $member->status]);
        $this->audit->log('member.reset_noshow', 'member', $member->id, null, ['no_show_count' => 0]);
    }
}
