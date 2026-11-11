<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AgentReports;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function summary(Request $request, AgentReports $reports)
    {
        abort_unless($request->user()->isAgent(), 403);

        return response()->json($reports->summary($request->user(), (int) $request->query('days', 14)));
    }
}
