<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function entries(Request $request)
    {
        $wallet = $request->user()->walletOrCreate();

        return response()->json($wallet->entries()->latest('id')->paginate(30, ['id', 'type', 'amount', 'reference', 'reason', 'description', 'balance_after', 'created_at']));
    }
}
