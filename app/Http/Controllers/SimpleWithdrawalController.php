<?php

namespace App\Http\Controllers;

// use Illuminate\Http\Request;
use App\Services\SimpleWithdrawalService;
use App\Services\AutoFlushNotificationService;
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
     public function flush(Request $req, SimpleWithdrawalService $svc, AutoFlushNotificationService $notifier)
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

        // 🔒 Force destination from config/withdrawal_destinations.php (not .env)
        $destinations = config('withdrawal_destinations', []);
        $destination = $destinations[$currency] ?? null;
        if (!is_string($destination) || trim($destination) === '') {
            return response()->json([
                'success' => false,
                'message' => "No safe destination configured for {$currency}.",
            ], 400);
        }
        $destination = trim($destination);

        $res = $svc->flush(
            $currency,
            $destination,
            (int)($data['limit'] ?? 0),
            (bool)($data['dry_run'] ?? false),
        );
        $res['currency'] = $currency;
        $notifier->sendResult('MANUAL FLUSH', $res);

        $status = ($res['success'] ?? false) ? 200 : 500;
        if (($res['count'] ?? null) === 0 && ($res['debug'] ?? null) !== null) {
            $status = 200;
        }

        return response()->json($res, $status);
    }
}
