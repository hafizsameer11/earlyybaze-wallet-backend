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
    public function index()
    {
        $walletCurrencies = $this->walletCurrencyService->all();
        return ResponseHelper::success($walletCurrencies, 'Wallet Currencies retrieved successfully', 200);
    }
}
