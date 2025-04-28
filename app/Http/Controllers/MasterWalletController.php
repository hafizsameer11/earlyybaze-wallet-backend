<?php

namespace App\Http\Controllers;

use App\Models\MasterWallet;
use App\Models\WalletCurrency;
use App\Services\BitcoinService;
use App\Services\BscService;
use App\Services\EthereumService;
use App\Services\LitecoinService;
use App\Services\MasterWalletService;
use App\Services\TronTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MasterWalletController extends Controller
{
    //
    protected $walletService, $EthService, $BitcoinService, $BscService, $LitecoinService, $TronService;

    public function __construct(MasterWalletService $walletService, EthereumService $EthService, BitcoinService $BitcoinService, BscService $BscService, LitecoinService $LitecoinService, TronTransferService $TronService)
    {
        $this->walletService = $walletService;
        $this->EthService = $EthService;
        $this->BitcoinService = $BitcoinService;
        $this->BscService = $BscService;
        $this->LitecoinService = $LitecoinService;
        $this->TronService = $TronService;
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
    // use Illuminate\Support\Arr;

    public function getMasterWalletDetails()
    {
        $totalWallets = MasterWallet::count();

        $masterWallet = MasterWallet::orderBy('created_at', 'desc')
            ->first();

        if (!$masterWallet) {
            return response()->json([
                'status' => 'error',
                'message' => 'No master wallet found'
            ], 404);
        }

        // Add symbol path
        $symbol = WalletCurrency::where('currency', 'ETH')->first();
        $symbolPath = $symbol ? asset('storage/' . $symbol->symbol) : null;
        $masterWallet->symbol = $symbolPath;

        // Fetch ETH and ERC-20 balances
        $balanceDetails = $this->EthService->getEthereumMasterBalances();

        // Exclude sensitive data
        $cleanWallet = Arr::except($masterWallet->toArray(), [
            'private_key',
            'mnemonic',
            'xpub',
            'response'
        ]);

        // Merge in balances
        $mergedWallet = array_merge($cleanWallet, $balanceDetails);

        return response()->json([
            'status' => 'success',
            'message' => 'Ethereum master wallet details fetched',
            'data' => [
                'totalWallets' => $totalWallets,
                'wallet' => [$mergedWallet]
            ]
        ]);
    }
    public function getMasterWalletBalance($id)
    {
        $masterWallet = MasterWallet::find($id);
        if (!$masterWallet) {
            return response()->json([
                'status' => 'error',
                'message' => 'No master wallet found'
            ], 404);
        }
        if ($masterWallet->blockchain == 'ethereum') {
            $balance = $this->EthService->getEthereumMasterBalances();
        }
        if ($masterWallet->blockchain == 'bitcoin') {
            $balance = $this->BitcoinService->getAddressBalance($masterWallet->address);
        }
        if ($masterWallet->blockchain == 'bsc') {
            $balance = $this->BscService->getBscMasterBalances();
        }
        if ($masterWallet->blockchain == 'litecoin') {
            $balance = $this->LitecoinService->getAddressBalance($masterWallet->address);
        }
        if ($masterWallet->blockchain == 'tron') {
            $balance = $this->TronService->getTrxBalance($masterWallet->address);
        }
        return response()->json($balance, 200);
    }
}
