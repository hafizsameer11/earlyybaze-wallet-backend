<?php

namespace App\Http\Controllers\V3;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExchangeRequest;
use App\Services\V3\V3ExchangeRateService;
use Illuminate\Http\Request;

class V3ExchangeRateController extends Controller
{
    public function __construct(private V3ExchangeRateService $service) {}

    public function indexZar()
    {
        try {
            $rates = $this->service->allByFiatAnchor('ZAR');

            return ResponseHelper::success($rates, 'ZAR exchange rates fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function getZar()
    {
        try {
            $rate = $this->service->getByCurrency('ZAR');

            return ResponseHelper::success($rate, 'Exchange rate fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function calculate(Request $request)
    {
        try {
            $result = $this->service->calculateFiatExchangeRate(
                $request->currency,
                $request->amount,
                $request->type,
                $request->to,
                $request->amount_in,
                $request->input('fiat_currency', 'ZAR')
            );

            return ResponseHelper::success($result, 'Exchange rate calculated successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function store(ExchangeRequest $request)
    {
        try {
            $rate = $this->service->create($request->validated());

            return ResponseHelper::success($rate, 'Exchange rate created successfully', 201);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function update($id, ExchangeRequest $request)
    {
        try {
            $rate = $this->service->update($id, $request->validated());

            return ResponseHelper::success($rate, 'Exchange rate updated successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
