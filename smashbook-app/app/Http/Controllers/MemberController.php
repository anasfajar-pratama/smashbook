<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MembershipType;
use App\Services\MemberService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status');
        $search = trim((string) $request->input('q'));

        $members = Member::query()
            ->with(['membershipType', 'activeSubscription.membershipType'])
            ->withCount(['bookings as bookings_count' => fn ($q) => $q->whereNotIn('status', ['cancelled', 'expired', 'no_show'])])
            ->withSum(['posSales as pos_total' => fn ($q) => $q->where('status', 'paid')], 'total_amount')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->when($status === 'aktif', function ($query) {
                $query->where('status', 'aktif')->whereHas('subscriptions', fn ($q) => $q->where('status', 'aktif')->whereDate('end_date', '>', now()->addDays(7)->toDateString()));
            })
            ->when($status === 'akan_expired', function ($query) {
                $query->whereHas('subscriptions', fn ($q) => $q->where('status', 'aktif')->whereDate('end_date', '>=', now()->toDateString())->whereDate('end_date', '<=', now()->addDays(7)->toDateString()));
            })
            ->when($status === 'expired', function ($query) {
                $query->where(function ($q) {
                    $q->where('status', 'expired')->orWhereHas('subscriptions', fn ($s) => $s->whereDate('end_date', '<', now()->toDateString()));
                });
            })
            ->when($status === 'perlu_konfirmasi', fn ($query) => $query->where('status', 'perlu_konfirmasi'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $types = MembershipType::query()->where('is_active', true)->withCount('members')->orderBy('price')->get();
        $noShowMembers = Member::query()
            ->withCount(['bookings as recent_noshow' => fn ($q) => $q->where('status', 'no_show')->whereDate('booking_date', '>=', now()->subDays(30)->toDateString())])
            ->where(function ($q) {
                $q->where('no_show_count', '>', 0)->orWhere('status', 'perlu_konfirmasi');
            })
            ->orderByDesc('no_show_count')
            ->get();

        $stats = [
            'active' => Member::query()->whereHas('subscriptions', fn ($q) => $q->where('status', 'aktif')->whereDate('end_date', '>=', now()->toDateString()))->count(),
            'expiring' => Member::query()->whereHas('subscriptions', fn ($q) => $q->where('status', 'aktif')->whereDate('end_date', '>=', now()->toDateString())->whereDate('end_date', '<=', now()->addDays(7)->toDateString()))->count(),
            'expired' => Member::query()->where(function ($q) {
                $q->where('status', 'expired')->orWhereHas('subscriptions', fn ($s) => $s->whereDate('end_date', '<', now()->toDateString()));
            })->count(),
            'deposit' => (float) Member::query()->sum('deposit_balance'),
        ];

        $detail = null;
        if ($request->filled('detail')) {
            $detail = Member::query()
                ->with([
                    'membershipType',
                    'activeSubscription.membershipType',
                    'subscriptions.membershipType',
                    'bookings.court',
                    'posSales',
                    'deposits',
                ])
                ->find($request->integer('detail'));
        }

        return view('members.index', compact('members', 'types', 'stats', 'status', 'search', 'detail', 'noShowMembers'));
    }

    public function store(Request $request, MemberService $members)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'unique:members,phone'],
            'email' => ['nullable', 'email', 'max:120'],
            'gender' => ['nullable', 'in:Laki-laki,Perempuan'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'member_type_id' => ['nullable', 'exists:membership_types,id'],
            'start_date' => ['required', 'date'],
            'initial_deposit' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:cash,qris,transfer,debit'],
            'deposit_method' => ['nullable', 'in:cash,qris,transfer,debit'],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $member = $members->create($data, $request->user());

        return back()->with('success', 'Member '.$member->name.' ('.$member->code.') berhasil dibuat.');
    }

    public function renew(Request $request, Member $member, MemberService $members)
    {
        $data = $request->validate([
            'membership_type_id' => ['required', 'exists:membership_types,id'],
            'start_date' => ['nullable', 'date'],
            'payment_method' => ['required', 'in:cash,qris,transfer,debit'],
            'reference_no' => ['nullable', 'string', 'max:80'],
        ]);

        $subscription = $members->renew(
            $member,
            MembershipType::findOrFail($data['membership_type_id']),
            ! empty($data['start_date']) ? Carbon::parse($data['start_date']) : null,
            $data['payment_method'],
            $data['reference_no'] ?? null,
            $request->user()
        );

        return back()->with('success', 'Membership diperpanjang sampai '.tanggal($subscription->end_date).'.');
    }

    public function topup(Request $request, Member $member, MemberService $members)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', 'in:cash,qris,transfer,debit'],
            'reference_no' => ['nullable', 'string', 'max:80'],
        ]);

        $members->topup($member, (float) $data['amount'], $data['method'], $data['reference_no'] ?? null, $request->user());

        return back()->with('success', 'Top-up deposit '.$member->name.' berhasil.');
    }

    public function resetNoShow(Member $member, MemberService $members)
    {
        $members->resetNoShow($member);

        return back()->with('success', 'Sanksi no-show member direset.');
    }
}
