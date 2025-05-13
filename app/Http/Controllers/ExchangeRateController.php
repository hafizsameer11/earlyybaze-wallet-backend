<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\ExchangeRequest;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    protected $exchangeRateService;
    public function __construct(ExchangeRateService $exchangeRateService)
    {
        $this->exchangeRateService = $exchangeRateService;
    }
    public function index()
    {
        try {
            $exchangeRates = $this->exchangeRateService->all();
            return ResponseHelper::success($exchangeRates, 'Exchange rates fetched successfully', 200);
        } catch (\Exception $e) {
            return  ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function store(ExchangeRequest $request)
    {
        try {
            $exchangeRate = $this->exchangeRateService->create($request->validated());
            return    ResponseHelper::success($exchangeRate, 'Exchange rate created successfully', 201);
        } catch (\Exception $e) {
            return   ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function update($id,ExchangeRequest $request){
        try {
            $exchangeRate = $this->exchangeRateService->update( $id,$request->validated());
            return    ResponseHelper::success($exchangeRate, 'Exchange rate updated successfully', 200);
        } catch (\Exception $e) {
            return   ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getNgNexchangeRate()
    {
        $exchangeRate = $this->exchangeRateService->getByCurrency('NGN');
        return    ResponseHelper::success($exchangeRate, 'Exchange rate fetched successfully', 200);
    }
    public function getByCurrency($currency)
    {
        try {
            $exchangeRate = $this->exchangeRateService->getByCurrency($currency);
            return    ResponseHelper::success($exchangeRate, 'Exchange rate fetched successfully', 200);
        } catch (\Exception $e) {
            return  ResponseHelper::error($e->getMessage(), 500);
        }
    }
    // public function update(ExchangeRequest $request, $id)
    // {
    //     try {
    //         $exchangeRate = $this->exchangeRateService->update($request->validated(), $id);
    //         return    ResponseHelper::success($exchangeRate, 'Exchange rate updated successfully', 200);
    //     } catch (\Exception $e) {
    //         return ResponseHelper::error($e->getMessage(), 500);
    //     }
    // }
    public function calculateExchangeRate(Request $request)
    {
        try {
            $amount = $request->amount;
            $currency = $request->currency;
            $type = $request->type;
            $to = $request->to;
            $amount_in=$request->amount_in;
            $exchangeRate = $this->exchangeRateService->calculateExchangeRate($currency, $amount, $type, $to,$amount_in);
            return    ResponseHelper::success($exchangeRate, 'Exchange rate calculated successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
