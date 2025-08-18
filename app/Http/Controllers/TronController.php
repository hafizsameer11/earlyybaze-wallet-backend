<?php

namespace App\Http\Controllers;
use App\Models\DepositAddress;
use App\Models\TransferLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class TronController extends Controller
{
     /**
     * Send native TRX using Tatum /v3/tron/transaction
     *
     * Body expects:
     * - address (string)        : the SENDER Tron address (Base58, starts with T)
     * - amount (numeric)        : amount in TRX (max 6 decimals)
     * - fee_limit_sun (optional): max fee in Sun (int). Default = 15 TRX (15_000_000 Sun)
     *
     * The recipient is HARD-CODED as requested.
     */
    public function transferTrx(Request $request)
    {
        $v = Validator::make($request->all(), [
            'address'        => ['required','string','min:34','max:50','regex:/^T[1-9A-HJ-NP-Za-km-z]{33}$/'],
            'amount'         => ['required','numeric','gt:0','regex:/^\d+(\.\d{1,6})?$/'],
            'fee_limit'  => ['nullable','integer','gte:0'],
        
        ], [
            'address.regex'  => 'Sender address is not a valid Tron Base58 address.',
            'amount.regex'   => 'Amount must have at most 6 decimal places.',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'code'    => 'VALIDATION_ERROR',
                'message' => 'Validation failed.',
                'errors'  => $v->errors(),
            ], 422);
        }

        $data          = $v->validated();
        $senderAddress = $data['address'];
        $amountTrx     = (float) $data['amount'];

        // HARD-CODED recipient (as per request)
        $toAddress = 'TVQaViN82jJeoCnc2JTNPHpGW3jXCJYmoY';

        // Default 15 TRX in Sun if not provided
        // $feeLimitSun = !empty($data['fee_limit']) ? (int)$data['fee_limit'] : 15_000_000;
        $feeLimitSun=$data['fee_limit'];

        // Lookup and decrypt private key (hex for Tron)
        $deposit = DepositAddress::where('address', $senderAddress)->first();
        if (!$deposit) {
            return response()->json([
                'success' => false,
                'message' => 'Deposit address (sender) not found in system.'
            ], 404);
        }

        try {
            $privateKeyHex = Crypt::decryptString($deposit->private_key);
        } catch (\Throwable $e) {
            Log::error('TRON decrypt error', ['err' => $e->getMessage(), 'addr' => $senderAddress]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to decrypt private key for sender address.'
            ], 500);
        }

        if (!is_string($privateKeyHex) || strlen($privateKeyHex) < 64) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Tron private key format (expected hex).'
            ], 422);
        }

        $apiKey = config('tatum.api_key', env('TATUM_API_KEY'));
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Tatum API key is not configured.'
            ], 500);
        }

        $payload = [
            'fromPrivateKey' => $privateKeyHex,
            'to'             => $toAddress,
            'amount'         => number_format($amountTrx, 6, '.', ''), // TRX supports 6 decimals
            'feeLimit'       => $feeLimitSun, // in Sun
        ];

        try {
            $resp = Http::withHeaders([
                'x-api-key'    => $apiKey,
                'accept'       => 'application/json',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.tatum.io/v3/tron/transaction', $payload);

            if ($resp->status() === 429) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tatum rate limit (429). Slow down or upgrade your plan.',
                    'tatum'   => $resp->json(),
                ], 429);
            }

            if ($resp->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tatum TRX transfer failed.',
                    'http'    => $resp->status(),
                    'tatum'   => $resp->json(),
                    'hint'    => 'Ensure sender has enough TRX for amount + bandwidth/energy (feeLimit).',
                ], $resp->status());
            }

            $body = $resp->json();

            TransferLog::create([
                'from_address' => $senderAddress,
                'to_address'   => $toAddress,
                'amount'       => $amountTrx,
                'currency'     => 'TRX',
                'tx'           => json_encode($body),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'TRX transaction submitted.',
                'result'  => $body,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Tatum TRX transfer exception', [
                'err'    => $e->getMessage(),
                'sender' => $senderAddress,
                'to'     => $toAddress,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Exception while calling Tatum.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send USDT (TRC-20) using Tatum /v3/tron/trc20/transaction
     *
     * Body expects:
     * - address (string)        : the SENDER Tron address (Base58, starts with T)
     * - amount (numeric)        : amount in USDT (TRC-20) (USDT has 6 decimals)
     * - fee_limit_sun (optional): max fee in Sun (int). Default = 15_000_000 Sun (15 TRX)
     *
     * The recipient is HARD-CODED as requested.
     * Contract address pulled from config/env to keep code clean.
     */
    public function transferUsdtTron(Request $request)
    {
        $v = Validator::make($request->all(), [
            'address'        => ['required','string','min:34','max:50','regex:/^T[1-9A-HJ-NP-Za-km-z]{33}$/'],
            'amount'         => ['required','numeric','gt:0','regex:/^\d+(\.\d{1,6})?$/'],
            'fee_limit'  => ['nullable','integer','gte:0'],
        ], [
            'address.regex'  => 'Sender address is not a valid Tron Base58 address.',
            'amount.regex'   => 'Amount must have at most 6 decimal places.',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'code'    => 'VALIDATION_ERROR',
                'message' => 'Validation failed.',
                'errors'  => $v->errors(),
            ], 422);
        }

        $data          = $v->validated();
        $senderAddress = $data['address'];
        $amountUsdt    = (float) $data['amount'];

        // HARD-CODED recipient (as per request)
        $toAddress = 'TVQaViN82jJeoCnc2JTNPHpGW3jXCJYmoY'; // TODO: replace with your recipient

        // Default feeLimit 15 TRX in Sun if not provided
        $feeLimitSun = $data['fee_limit'];

        // USDT (TRC-20) contract address from env/config
        $usdtContract = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
        if (!$usdtContract) {
            return response()->json([
                'success' => false,
                'message' => 'USDT (TRC-20) contract address is not configured (TRON_USDT_CONTRACT).'
            ], 500);
        }

        // Lookup and decrypt private key (hex for Tron)
        $deposit = DepositAddress::where('address', $senderAddress)->first();
        if (!$deposit) {
            return response()->json([
                'success' => false,
                'message' => 'Deposit address (sender) not found in system.'
            ], 404);
        }

        try {
            $privateKeyHex = Crypt::decryptString($deposit->private_key);
        } catch (\Throwable $e) {
            Log::error('TRON decrypt error', ['err' => $e->getMessage(), 'addr' => $senderAddress]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to decrypt private key for sender address.'
            ], 500);
        }

        if (!is_string($privateKeyHex) || strlen($privateKeyHex) < 64) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Tron private key format (expected hex).'
            ], 422);
        }

        $apiKey = config('tatum.api_key', env('TATUM_API_KEY'));
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Tatum API key is not configured.'
            ], 500);
        }

        $payload = [
            'fromPrivateKey' => $privateKeyHex,
            'to'             => $toAddress,
            'amount'         => number_format($amountUsdt, 6, '.', ''), // USDT typically 6 decimals
            'tokenAddress'=> $usdtContract,
            'feeLimit'       => $feeLimitSun, // in Sun
        ];

        try {
            $resp = Http::withHeaders([
                'x-api-key'    => $apiKey,
                'accept'       => 'application/json',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.tatum.io/v3/tron/trc20/transaction', $payload);

            if ($resp->status() === 429) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tatum rate limit (429). Slow down or upgrade your plan.',
                    'tatum'   => $resp->json(),
                ], 429);
            }

            if ($resp->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tatum USDT (TRC-20) transfer failed.',
                    'http'    => $resp->status(),
                    'tatum'   => $resp->json(),
                    'hint'    => 'Ensure sender has enough TRX to cover energy (feeLimit) and sufficient USDT balance.',
                ], $resp->status());
            }

            $body = $resp->json();

            TransferLog::create([
                'from_address' => $senderAddress,
                'to_address'   => $toAddress,
                'amount'       => $amountUsdt,
                'currency'     => 'USDT_TRON',
                'tx'           => json_encode($body),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'USDT (TRC-20) transaction submitted.',
                'result'  => $body,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Tatum USDT_TRON transfer exception', [
                'err'    => $e->getMessage(),
                'sender' => $senderAddress,
                'to'     => $toAddress,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Exception while calling Tatum.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
