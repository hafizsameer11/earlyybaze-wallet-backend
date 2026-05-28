<?php

namespace App\Http\Controllers\V3;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FeeController;
use App\Models\Fee;
use App\Services\FiatBalanceService;
use Exception;
use Illuminate\Http\Request;

class V3FeeController extends Controller
{
    public function calculateWithdrawFee(Request $request)
    {
        try {
            $amount = (string) $request->input('amount');
            $currency = FiatBalanceService::normalizeCurrency($request->all());
            $feeType = FiatBalanceService::withdrawFeeType($currency);

            $fee = Fee::where('type', $feeType)->orderByDesc('id')->first()
                ?? Fee::where('type', 'withdraw')->orderByDesc('id')->first();

            if (! $fee) {
                throw new Exception('No withdraw fee defined for '.$currency);
            }

            $percentageFee = bcmul($amount, bcdiv((string) $fee->percentage, '100', 8), 8);
            $fixedFee = (string) ($fee->amount ?? 0);
            $calculatedFee = bcadd($percentageFee, $fixedFee, 8);

            return ResponseHelper::success([
                'fee' => $calculatedFee,
                'amount' => $amount,
                'currency' => $currency,
                'feeObject' => $fee,
                'breakdown' => [
                    'percentage_fee' => $percentageFee,
                    'fixed_fee' => $fixedFee,
                ],
            ], 'Fee calculated successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function getByType(string $type)
    {
        return app(FeeController::class)->getByType($type);
    }

    public function getAll()
    {
        return app(FeeController::class)->getAll();
    }
}
