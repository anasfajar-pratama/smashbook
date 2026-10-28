<?php

namespace App\Http\Controllers;

use App\Services\ReportService;

class DashboardController extends Controller
{
    public function index(ReportService $reports)
    {
        return view('dashboard', $reports->dashboard());
    }
}
