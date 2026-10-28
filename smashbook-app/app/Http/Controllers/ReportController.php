<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        $tab = $request->input('tab', 'pendapatan');

        try {
            $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
            $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        } catch (\Throwable) {
            $from = now()->startOfMonth();
            $to = now();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366);
        }

        $data = match ($tab) {
            'okupansi' => $reports->occupancyReport($from, $to),
            'kas' => $reports->cashReport($from, $to),
            'stok' => $reports->stockReport($from, $to),
            'member' => $reports->memberReport($from, $to),
            'labarugi' => $reports->profitLoss($from, $to),
            default => $reports->revenueReport($from, $to),
        };

        return view('reports.index', compact('tab', 'from', 'to', 'data'));
    }
}
