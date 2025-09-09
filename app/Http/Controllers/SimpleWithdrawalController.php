<?php

namespace App\Http\Controllers;

// use Illuminate\Http\Request;
use App\Services\SimpleWithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SimpleWithdrawalController extends Controller
{
    //
    /**
     * Body:
     *  - currency (string)        e.g., BTC | TRX | USDT_TRON | ETH | USDT_ETH | BNB | USDT_BSC | MATIC | USDT_POLYGON
     *  - destination (string)     recipient address
     *  - limit? (int)             cap rows processed this call
     *  - dry_run? (bool)          don't broadcast, just return plan
     */
     public function flush(Request $req, SimpleWithdrawalService $svc)
    {
        $v = Validator::make($req->all(), [
            'currency' => ['required','string','max:32'],
            'limit'    => ['nullable','integer','min:1','max:5000'],
            'dry_run'  => ['nullable','boolean'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'code'    => 'VALIDATION_ERROR',
                'errors'  => $v->errors(),
            ], 422);
        }

        $data = $v->validated();
        $currency = strtoupper($data['currency']);

        // 🔒 Force destination from config
        $destinations = config('withdrawal_destinations');
        if (!isset($destinations[$currency])) {
            return response()->json([
                'success' => false,
                'message' => "No safe destination configured for {$currency}.",
            ], 400);
        }
        $destination = $destinations[$currency];

        $res = $svc->flush(
            $currency,
            $destination,
            (int)($data['limit'] ?? 0),
            (bool)($data['dry_run'] ?? false),
        );

        return response()->json($res, $res['success'] ? 200 : 500);
    }
}
