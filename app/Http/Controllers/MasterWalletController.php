<?php

namespace App\Http\Controllers;

use App\Models\MasterWallet;
use App\Models\WalletCurrency;
use App\Services\EthereumService;
use App\Services\MasterWalletService;
use Illuminate\Http\Request;

class MasterWalletController extends Controller
{
    //
    protected $walletService, $EthService;

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
    public function getEthBalance()
    {
        $balance = $this->EthService->getEthereumMasterBalances();
        return response()->json($balance, 200);
    }
    public function getMasterWalletDetails()
    {
        $totalWallets = MasterWallet::where('blockchain', 'ethereum')->count();

        $masterWallet = MasterWallet::where('blockchain', 'ethereum')->orderBy('created_at', 'desc')->first();

        if (!$masterWallet) {
            return response()->json([
                'status' => 'error',
                'message' => 'No master wallet found'
            ], 404);
        }
        $symbol = WalletCurrency::where('currency', 'ETH')->first();


        $symbol = asset('storage/' . $symbol->symbol);

        $masterWallet->symbol = $symbol;
        // Get balances (ETH + tokens)
        $balanceDetails = $this->EthService->getEthereumMasterBalances();

        // Merge into master wallet object
        $mergedWallet = array_merge(
            $masterWallet->toArray(),
            $balanceDetails
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Ethereum master wallet details fetched',
            'data' => [
                'totalWallets' => $totalWallets,
                'wallet' => [$mergedWallet]
            ]
        ]);
    }
}
