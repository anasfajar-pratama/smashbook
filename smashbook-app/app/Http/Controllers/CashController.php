<?php

namespace App\Http\Controllers;

use App\Models\CashDepositBank;
use App\Models\CashSession;
use App\Models\User;
use App\Services\CashService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CashController extends Controller
{
    public function index(Request $request, CashService $cash)
    {
        $active = $cash->activeSession($request->user());
        $tab = $request->input('tab', 'aktif');

        $sessionHistory = CashSession::query()
            ->with(['cashier', 'verifier'])
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('cashier_id', $request->user()->id))
            ->latest('opened_at')
            ->paginate(15)
            ->withQueryString();

        $recap = $cash->recap(now(), $request->user()->isAdmin() ? null : $request->user());
        $methodTotals = $active ? $cash->paymentsByMethod($active) : null;
        $pettyCash = $active
            ? $active->movements()->where('type', 'out')->whereIn('category', ['kas_kecil', 'pembelian'])->get()
            : collect();
        $ledger = $active ? $active->movements()->with('creator')->oldest('created_at')->get() : collect();
        $bankDeposits = CashDepositBank::query()->with('creator')->latest('deposit_date')->limit(10)->get();

        $expected = $active?->expectedBalance() ?? 0;

        return view('cash.index', compact(
            'active', 'tab', 'sessionHistory', 'recap', 'methodTotals', 'pettyCash',
            'ledger', 'bankDeposits', 'expected'
        ));
    }

    public function open(Request $request, CashService $cash)
    {
        $data = $request->validate([
            'shift' => ['required', 'in:pagi,sore,malam'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
        ]);

        $cash->open($request->user(), $data['shift'], (float) $data['opening_balance']);

        return back()->with('success', 'Sesi kas shift '.ucfirst($data['shift']).' dibuka.');
    }

    public function close(Request $request, CashService $cash)
    {
        $data = $request->validate([
            'physical_balance' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'owner_pin' => ['nullable', 'string', 'max:10'],
        ]);

        $session = $cash->activeSession($request->user());

        if (! $session) {
            return back()->with('error', 'Tidak ada sesi kas terbuka.');
        }

        $expected = $session->expectedBalance();
        $difference = (float) $data['physical_balance'] - $expected;
        $tolerance = (float) setting('cash_difference_tolerance', 10000);

        $verifier = $request->user();

        if (abs($difference) > $tolerance) {
            $verifier = User::query()->where('role', 'owner')->where('is_active', true)->get()
                ->first(fn ($owner) => $owner->verifyPin($data['owner_pin'] ?? null));

            if (! $verifier) {
                throw ValidationException::withMessages(['owner_pin' => 'Selisih melebihi toleransi. PIN Owner wajib valid.']);
            }
        }

        $cash->close($session, (float) $data['physical_balance'], $data['notes'] ?? null, $verifier);

        return back()->with('success', 'Shift ditutup. Selisih kas: '.rupiah($difference).'.');
    }

    public function movement(Request $request, CashService $cash)
    {
        $data = $request->validate([
            'type' => ['required', 'in:in,out'],
            'category' => ['required', 'string', 'max:40'],
            'amount' => ['required', 'numeric', 'min:1'],
            'description' => ['required', 'string', 'max:160'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        if ($data['type'] === 'out' && $data['category'] === 'kas_kecil' && ! $request->hasFile('attachment')) {
            throw ValidationException::withMessages(['attachment' => 'Nota wajib dilampirkan untuk kas keluar.']);
        }

        $attachment = $request->file('attachment')?->store('receipts', 'public');

        if ($data['type'] === 'in') {
            $cash->recordCashIn((float) $data['amount'], $data['category'], $data['description'], null, null, null, $request->user(), $attachment);
        } else {
            $cash->recordCashOut((float) $data['amount'], $data['category'], $data['description'], null, null, $request->user(), $attachment);
        }

        return back()->with('success', 'Mutasi kas dicatat.');
    }

    public function deposit(Request $request, CashService $cash)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'bank' => ['required', 'string', 'max:80'],
            'deposit_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:80'],
        ]);

        $deposit = CashDepositBank::create([
            'amount' => $data['amount'],
            'bank' => $data['bank'],
            'deposit_date' => $data['deposit_date'],
            'reference' => $data['reference'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $cash->recordCashOut(
            (float) $data['amount'],
            'setoran_bank',
            'Setoran ke '.$data['bank'],
            'cash_deposit',
            $deposit->id,
            $request->user()
        );

        return back()->with('success', 'Setoran bank dicatat dan kas fisik dikurangi.');
    }
}
