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
            // return response()->json($exchangeRates, 200);
            ResponseHelper::success($exchangeRates, 'Exchange rates fetched successfully', 200);
        } catch (\Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function store(ExchangeRequest $request)
    {
        try {
            $exchangeRate = $this->exchangeRateService->create($request->all());
            ResponseHelper::success($exchangeRate, 'Exchange rate created successfully', 201);
        } catch (\Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getByCurrency($currency)
    {
        try {
            $exchangeRate = $this->exchangeRateService->getByCurrency($currency);
            ResponseHelper::success($exchangeRate, 'Exchange rate fetched successfully', 200);
        } catch (\Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function update(ExchangeRequest $request, $id)
    {
        try {
            $exchangeRate = $this->exchangeRateService->update($request->all(), $id);
            ResponseHelper::success($exchangeRate, 'Exchange rate updated successfully', 200);
        } catch (\Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
