<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PricingService;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function users()
    {
        $users = User::query()->with('activeCashSession')->orderBy('name')->get();

        return view('admin.users', compact('users'));
    }

    public function storeUser(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'in:owner,admin,kasir,operator'],
            'password' => ['required', Password::min(8)],
            'pin' => ['nullable', 'digits_between:4,6'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'password' => $data['password'],
            'pin_hash' => ! empty($data['pin']) ? Hash::make($data['pin']) : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $audit->log('user.create', 'user', $user->id, null, $user->only(['name', 'email', 'role', 'is_active']));

        return back()->with('success', 'Pengguna '.$user->name.' ditambahkan.');
    }

    public function updateUser(Request $request, User $user, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'in:owner,admin,kasir,operator'],
            'password' => ['nullable', Password::min(8)],
            'pin' => ['nullable', 'digits_between:4,6'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($user->id === $request->user()->id && ! $request->boolean('is_active', true)) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        $before = $user->only(['name', 'email', 'phone', 'role', 'is_active']);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        if (! empty($data['pin'])) {
            $user->pin_hash = Hash::make($data['pin']);
        }

        $user->save();

        $audit->log('user.update', 'user', $user->id, $before, $user->only(['name', 'email', 'phone', 'role', 'is_active']));

        return back()->with('success', 'Pengguna diperbarui.');
    }

    public function settings(SettingService $settings)
    {
        $groups = [
            'Identitas GOR' => [
                'venue_name' => ['label' => 'Nama GOR', 'type' => 'text'],
                'venue_address' => ['label' => 'Alamat', 'type' => 'textarea'],
                'venue_phone' => ['label' => 'Telepon', 'type' => 'text'],
                'receipt_footer' => ['label' => 'Footer struk', 'type' => 'textarea'],
            ],
            'Operasional & Booking' => [
                'opening_time' => ['label' => 'Jam buka', 'type' => 'text'],
                'closing_time' => ['label' => 'Jam tutup', 'type' => 'text'],
                'hold_minutes' => ['label' => 'Masa hold (menit)', 'type' => 'number'],
                'public_advance_days' => ['label' => 'Booking umum (hari ke depan)', 'type' => 'number'],
                'member_advance_days' => ['label' => 'Booking member (hari ke depan)', 'type' => 'number'],
                'dp_percent' => ['label' => 'DP default (%)', 'type' => 'number'],
                'cash_difference_tolerance' => ['label' => 'Toleransi selisih kas (Rp)', 'type' => 'number'],
            ],
            'POS & Approval' => [
                'discount_limit_pct' => ['label' => 'Batas diskon kasir (%)', 'type' => 'number'],
                'po_admin_threshold' => ['label' => 'Threshold auto-approve Admin (Rp)', 'type' => 'number'],
                'po_owner_threshold' => ['label' => 'Batas approval Admin (Rp)', 'type' => 'number'],
                'po_urgent_cash_threshold' => ['label' => 'Auto-approve darurat tunai (Rp)', 'type' => 'number'],
                'po_cash_receipt_threshold' => ['label' => 'Wajib nota tunai di bawah (Rp)', 'type' => 'number'],
            ],
        ];

        $values = collect($groups)->flatMap(fn ($fields) => array_keys($fields))
            ->mapWithKeys(fn ($key) => [$key => $settings->get($key)])
            ->all();

        return view('admin.settings', compact('groups', 'values'));
    }

    public function updateSettings(Request $request, SettingService $settings, PricingService $pricing, AuditLogger $audit)
    {
        $allowed = [
            'venue_name', 'venue_address', 'venue_phone', 'receipt_footer',
            'opening_time', 'closing_time', 'hold_minutes', 'public_advance_days',
            'member_advance_days', 'dp_percent', 'cash_difference_tolerance',
            'discount_limit_pct', 'po_admin_threshold', 'po_owner_threshold',
            'po_urgent_cash_threshold', 'po_cash_receipt_threshold',
        ];

        $data = $request->validate([
            'venue_name' => ['required', 'string', 'max:120'],
            'venue_address' => ['nullable', 'string', 'max:500'],
            'venue_phone' => ['nullable', 'string', 'max:30'],
            'receipt_footer' => ['nullable', 'string', 'max:300'],
            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$|^24:00$/'],
            'hold_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'public_advance_days' => ['required', 'integer', 'min:0', 'max:90'],
            'member_advance_days' => ['required', 'integer', 'min:0', 'max:365'],
            'dp_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'cash_difference_tolerance' => ['required', 'numeric', 'min:0'],
            'discount_limit_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'po_admin_threshold' => ['required', 'numeric', 'min:0'],
            'po_owner_threshold' => ['required', 'numeric', 'min:0'],
            'po_urgent_cash_threshold' => ['required', 'numeric', 'min:0'],
            'po_cash_receipt_threshold' => ['required', 'numeric', 'min:0'],
        ]);

        if ($pricing->toMinutes($data['closing_time']) <= $pricing->toMinutes($data['opening_time'])) {
            throw ValidationException::withMessages(['closing_time' => 'Jam tutup harus lebih besar dari jam buka.']);
        }

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $settings->set($key, $data[$key]);
            }
        }

        $audit->log('settings.update', 'settings', null, null, $data);

        return back()->with('success', 'Pengaturan sistem disimpan.');
    }

    public function audit(Request $request)
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('entity'), fn ($q) => $q->where('entity', $request->input('entity')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        $users = User::query()->orderBy('name')->get();
        $entities = AuditLog::query()->distinct()->orderBy('entity')->pluck('entity');

        return view('admin.audit', compact('logs', 'users', 'entities'));
    }
}
