<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminReports;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AdminReports $reports)
    {
        return Inertia::render('Admin/Dashboard', ['stats' => $reports->dashboard((int) $request->query('days', 14))]);
    }
}
