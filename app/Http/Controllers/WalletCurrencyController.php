<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\WalletCurrencyRequest;
use App\Services\WalletCurrencyService;

class WalletCurrencyController extends Controller
{
    protected $walletCurrencyService;
    public function __construct(WalletCurrencyService $walletCurrencyService)
    {
        $this->walletCurrencyService = $walletCurrencyService;
    }
    public function create(WalletCurrencyRequest $request)
    {
        try {
            $walletCurrency = $this->walletCurrencyService->create($request->validated());
            return ResponseHelper::success($walletCurrency, 'Wallet Currency created successfully', 201);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function update($id, WalletCurrencyRequest $request)
    {
        try {
            $walletCurrency = $this->walletCurrencyService->update($id, $request->validated());
            return ResponseHelper::success($walletCurrency, 'Wallet Currency updated successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function index()
    {
        $walletCurrencies = $this->walletCurrencyService->all();
        return ResponseHelper::success($walletCurrencies, 'Wallet Currencies retrieved successfully', 200);
    }
    public function ngnCurrency()
    {
        $walletCurrencies = $this->walletCurrencyService->ngnCurrency();
        return ResponseHelper::success($walletCurrencies, 'Wallet Currencies retrieved successfully', 200);
    }

    public function getNetworks($currency_id)
    {
        try {
            $networks = $this->walletCurrencyService->getNetworks($currency_id);
            return ResponseHelper::success($networks, 'Networks fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
}
