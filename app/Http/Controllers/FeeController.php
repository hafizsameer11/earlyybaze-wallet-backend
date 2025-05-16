<?php

namespace App\Http\Controllers;

use App\Helpers\ExchangeFeeHelper;
use App\Helpers\ResponseHelper;
use App\Http\Requests\FeeRequest;
use App\Models\Fee;
use App\Models\MasterWallet;
use App\Models\WalletCurrency;
use App\Services\FeeService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeeController extends Controller
{
    protected $feeService;
    // protected $responseHelper;
    public function __construct(FeeService $feeService)
    {
        $this->feeService = $feeService;
    }
    public function create(FeeRequest $request)
    {
        try {
            $fee = $this->feeService->create($request->validated());
            return ResponseHelper::success($fee, 'Fee created successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function update(FeeRequest $request, $id)
    {
        try {
            $fee = $this->feeService->update($id, $request->validated());
            return ResponseHelper::success($fee, 'Fee updated successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getByType($type)
    {
        try {
            $fee = $this->feeService->getByType($type);
            return ResponseHelper::success($fee, 'Fee fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getAll()
    {
        try {
            $fee = $this->feeService->getAll();
            return ResponseHelper::success($fee, 'Fee fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function calculateFee(Request $request)
    {
        try {
            if ($request->methode && $request->methode == 'external_transfer') {
                $walletCurrency = WalletCurrency::where('currency', $request->currency)->first();
                $masterWalletAddress = MasterWallet::where('blockchain', $walletCurrency->blockchain)->first();
                $masterWalletAddress = $masterWalletAddress->address;
            }
            $fee = ExchangeFeeHelper::caclulateFee(
                $request->amount,
                $request->currency,
                $request->type,

                $request->methode ?? null,
                $masterWalletAddress ?? null,
                $request->to ?? null,
                Auth::user()->id
            );
            return ResponseHelper::success($fee, 'Fee calculated successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function calculateWithdrawFee(Request $request)
    {
        try {
            $amount = $request->amount;
            $fee = Fee::where('type', 'withdraw')->orderBy('id', 'desc')->first();
            $calculatedFee = bcmul($amount, $fee->percentage, 8);
            return ResponseHelper::success($calculatedFee, 'Fee calculated successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
