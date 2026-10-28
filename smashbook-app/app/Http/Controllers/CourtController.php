<?php

namespace App\Http\Controllers;

use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\CourtPriceRule;
use App\Models\Holiday;
use App\Services\AuditLogger;
use App\Services\PricingService;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CourtController extends Controller
{
    public function index(PricingService $pricing)
    {
        $courts = Court::query()->with('priceRules')->orderBy('sort_order')->get();
        $holidays = Holiday::query()->orderBy('date')->get();
        $hours = $pricing->operatingHours();

        $occupancy = $courts->mapWithKeys(function ($court) use ($hours) {
            $slots = BookingSlot::query()
                ->where('court_id', $court->id)
                ->whereBetween('slot_date', [now()->subDays(6)->toDateString(), now()->toDateString()])
                ->whereHas('booking', fn ($q) => $q->whereNotIn('status', ['cancelled', 'no_show', 'expired']))
                ->count();
            $available = max(count($hours) * 2 * 7, 1);

            return [$court->id => min(100, round($slots / $available * 100))];
        });

        $settings = [
            'opening_time' => setting('opening_time', '07:00'),
            'closing_time' => setting('closing_time', '24:00'),
            'hold_minutes' => setting('hold_minutes', 15),
            'public_advance_days' => setting('public_advance_days', 14),
            'member_advance_days' => setting('member_advance_days', 30),
            'dp_percent' => setting('dp_percent', 50),
            'discount_limit_pct' => setting('discount_limit_pct', 20),
            'cash_difference_tolerance' => setting('cash_difference_tolerance', 10000),
        ];

        return view('courts.index', compact('courts', 'holidays', 'hours', 'occupancy', 'settings'));
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:courts,code'],
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', 'string', 'max:40'],
            'surface' => ['nullable', 'string', 'max:60'],
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $court = Court::create($data + ['is_active' => true]);
        $audit->log('court.create', 'court', $court->id, null, $court->toArray());

        return back()->with('success', 'Lapangan '.$court->name.' ditambahkan.');
    }

    public function update(Request $request, Court $court, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', 'string', 'max:40'],
            'surface' => ['nullable', 'string', 'max:60'],
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $before = $court->toArray();
        $court->update($data);
        $audit->log('court.update', 'court', $court->id, $before, $court->toArray());

        return back()->with('success', 'Data lapangan diperbarui.');
    }

    public function toggle(Court $court, AuditLogger $audit)
    {
        $before = $court->toArray();
        $court->update(['is_active' => ! $court->is_active]);
        $audit->log('court.toggle', 'court', $court->id, $before, ['is_active' => $court->is_active]);

        return back()->with('success', 'Status '.$court->name.' diubah menjadi '.($court->is_active ? 'aktif' : 'nonaktif').'.');
    }

    public function storePriceRule(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'court_id' => ['required', 'exists:courts,id'],
            'day_type' => ['required', 'in:weekday,weekend,all'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'price_per_hour' => ['required', 'numeric', 'min:0'],
            'label' => ['required', 'string', 'max:60'],
        ]);

        $rule = CourtPriceRule::create($data);
        $audit->log('price_rule.create', 'court_price_rule', $rule->id, null, $rule->toArray());

        return back()->with('success', 'Aturan tarif ditambahkan.');
    }

    public function destroyPriceRule(CourtPriceRule $rule, AuditLogger $audit)
    {
        $before = $rule->toArray();
        $rule->delete();
        $audit->log('price_rule.delete', 'court_price_rule', $rule->id, $before, null);

        return back()->with('success', 'Aturan tarif dihapus.');
    }

    public function storeHoliday(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'unique:holidays,date'],
            'name' => ['required', 'string', 'max:80'],
            'is_peak' => ['nullable', 'boolean'],
        ]);

        $holiday = Holiday::create($data + ['is_peak' => $request->boolean('is_peak', true)]);
        $audit->log('holiday.create', 'holiday', $holiday->id, null, $holiday->toArray());

        return back()->with('success', 'Hari libur ditambahkan.');
    }

    public function destroyHoliday(Holiday $holiday, AuditLogger $audit)
    {
        $before = $holiday->toArray();
        $holiday->delete();
        $audit->log('holiday.delete', 'holiday', $holiday->id, $before, null);

        return back()->with('success', 'Hari libur dihapus.');
    }

    public function updateSettings(Request $request, SettingService $settings, PricingService $pricing)
    {
        $data = $request->validate([
            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$|^24:00$/'],
            'hold_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'public_advance_days' => ['required', 'integer', 'min:0', 'max:90'],
            'member_advance_days' => ['required', 'integer', 'min:0', 'max:365'],
            'dp_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'discount_limit_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'cash_difference_tolerance' => ['required', 'numeric', 'min:0'],
        ]);

        if ($pricing->toMinutes($data['closing_time']) <= $pricing->toMinutes($data['opening_time'])) {
            throw ValidationException::withMessages(['closing_time' => 'Jam tutup harus lebih besar dari jam buka.']);
        }

        foreach ($data as $key => $value) {
            $settings->set($key, $value);
        }

        return back()->with('success', 'Pengaturan operasional disimpan.');
    }
}
