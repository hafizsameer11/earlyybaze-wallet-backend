<?php

namespace App\Http\Controllers;

use App\Services\EthereumService;
use App\Services\MasterWalletService;
use Illuminate\Http\Request;

class MasterWalletController extends Controller
{
    //
    protected $walletService,$EthService;

    public function __construct(MasterWalletService $walletService, EthereumService $EthService)
    {
        $this->walletService = $walletService;
        $this->EthService = $EthService;
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'blockchain' => 'required|string',
            'endpoint' => 'required|string'
        ]);

        try {
            $wallet = $this->walletService->createMasterWallet($validated['blockchain'], $validated['endpoint']);
            return response()->json(['message' => 'Master wallet created', 'wallet' => $wallet], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function index()
    {
        $wallets = $this->walletService->getMasterWallets();
        return response()->json($wallets, 200);
    }
    public function getEthBalance(){
        $balance = $this->EthService->getEthereumMasterBalances();
        return response()->json($balance, 200);
    }
}
