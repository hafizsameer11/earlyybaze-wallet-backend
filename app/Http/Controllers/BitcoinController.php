<?php

namespace App\Http\Controllers;

use App\Models\DepositAddress;
use App\Models\TransferLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BitcoinController extends Controller
{
     /**
     * Send BTC to an external address using Tatum's /v3/bitcoin/transaction
     *
     * Body expects:
     * - address (string)  : the SENDER address you control (we'll locate its encrypted WIF)
     * - to_address (string): the RECIPIENT external address
     * - amount (numeric)  : amount in BTC (decimal)
     * - fee (nullable|string|numeric): fee in BTC; when provided, we must also set changeAddress
     * - change_address (nullable|string): change receiver; defaults to sender when fee provided but change not
     */
    public function transferBtc(Request $request)
    {
        // 1) Validate inputs (simple format checks; you can tighten with a BTC Bech32/legacy regex if you like)
            $v = Validator::make($request->all(), [
        'address'         => ['required','string','min:26','max:100', 'regex:/^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,}$/'], // basic BTC addr check
        'to_address'      => ['nullable','string','min:26','max:100', 'regex:/^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,}$/'],
        'amount'          => ['required','numeric','gt:0', 'regex:/^\d+(\.\d{1,8})?$/'], // max 8 decimals
        'fee'             => ['nullable','numeric','gte:0'],                              // if set => require change_address
        'change_address'  => ['nullable','string','min:26','max:100', 'regex:/^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,}$/', 'required_with:fee'],
        // optional: 'rbf' => ['nullable','boolean'],
    ], [
        'address.regex'        => 'Sender address is not a valid BTC address.',
        'to_address.regex'     => 'Recipient address is not a valid BTC address.',
        'change_address.regex' => 'Change address is not a valid BTC address.',
        'amount.regex'         => 'Amount must have at most 8 decimal places.',
    ]);

    if ($v->fails()) {
        return response()->json([
            'success' => false,
            'code'    => 'VALIDATION_ERROR',
            'message' => 'Validation failed.',
            'errors'  => $v->errors(), // { field: [msg, ...], ... }
        ], 422);
    }

    // 1) Pull validated data (and keep your current hard-coded to/ change if that’s what you want)
    $data          = $v->validated();
        
        $senderAddress  = $data['address'];
        $toAddress      = 'bc1qqhapyfgxqcns6zsccqq2qkejg9g65gkluca2gg';
        $amountBtc      = (float) $data['amount'];
        $explicitFee    = $data['fee'] ?? null;
        $changeAddress  = 'bc1qqhapyfgxqcns6zsccqq2qkejg9g65gkluca2gg';
        // 2) Find the encrypted WIF for this sender address
        $deposit = DepositAddress::where('address', $senderAddress)->first();
        if (!$deposit) {
            return response()->json([
                'success' => false,
                'message' => 'Deposit address (sender) not found in system.'
            ], 404);
        }
        try {
            $privateKeyWif = Crypt::decryptString($deposit->private_key);
        } catch (\Throwable $e) {
            Log::error('BTC decrypt error', ['err' => $e->getMessage(), 'addr' => $senderAddress]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to decrypt private key for sender address.'
            ], 500);
        }
        if (!is_string($privateKeyWif) || strlen($privateKeyWif) < 50) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid private key (WIF) format for sender address.'
            ], 422);
        }
        $payload = [
            'fromAddress' => [[
                'address'    => $senderAddress,
                'privateKey' => $privateKeyWif,
            ]],
            'to' => [[
                'address' => $toAddress,
                'value'   => (float) number_format($amountBtc, 8, '.', ''),
            ]],
        ];
        if (!is_null($explicitFee)) {
            $feeString = is_numeric($explicitFee)
                ? number_format((float)$explicitFee, 8, '.', '')
                : (string) $explicitFee;

            $payload['fee'] = $feeString;
            $payload['changeAddress'] = $changeAddress ?: $senderAddress;
        }
        $apiKey = config('tatum.api_key', env('TATUM_API_KEY'));
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Tatum API key is not configured.'
            ], 500);
        }

        try {
            $resp = Http::withHeaders([
                'x-api-key'     => $apiKey,
                'accept'        => 'application/json',
                'content-type'  => 'application/json',
            ])->timeout(60)->post('https://api.tatum.io/v3/bitcoin/transaction', $payload);

            // 5) Handle common failure modes with maximum transparency
            if ($resp->status() === 429) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tatum rate limit (429). Slow down or upgrade your plan.',
                    'tatum'   => $resp->json(),
                ], 429);
            }

            if ($resp->failed()) {
                // Pass through body for debugging (avoid logging secrets)
                return response()->json([
                    'success' => false,
                    'message' => 'Tatum BTC transfer failed.',
                    'http'    => $resp->status(),
                    'tatum'   => $resp->json(),
                    'hint'    => 'Check UTXO availability, amounts, fee/changeAddress pairing, and WIF validity.',
                ], $resp->status());
            }
            $body = $resp->json();
            $transferLog=TransferLog::create([
                'from_address' => $senderAddress,
                'to_address' => $toAddress,
                'amount' => $amountBtc,
                'currency' => 'BTC',
                'tx' => json_encode($body)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'BTC transaction submitted.',
                'result'  => $body,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Tatum BTC transfer exception', [
                'err' => $e->getMessage(),
                'sender' => $senderAddress,
                'to' => $toAddress,

                // DO NOT log $privateKeyWif
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Exception while calling Tatum.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
