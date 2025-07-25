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
use Illuminate\Support\Facades\Log;

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

    //   use Illuminate\Support\Arr;

public function getMasterWalletDetails()
{
    $masterWallets = MasterWallet::orderBy('created_at', 'desc')->get();
    $totalWallets = $masterWallets->count();

    if ($masterWallets->isEmpty()) {
        return response()->json([
            'status' => 'error',
            'message' => 'No master wallets found'
        ], 404);
    }

    $walletsWithBalances = $masterWallets->map(function ($wallet) {
        // Symbol path
        $symbol = WalletCurrency::where('blockchain', $wallet->blockchain)->first();
        $wallet->symbol = $symbol ? asset('storage/' . $symbol->symbol) : null;

        // Initialize defaults
        $ethBalance = "0.00000000";
        $tokenBalances = [];

        try {
            switch (strtolower($wallet->blockchain)) {
                case 'ethereum':
                    $balance = $this->EthService->getEthereumMasterBalances();
                    $ethBalance = $balance['eth_balance'] ?? "0.00000000";
                    $tokenBalances = $balance['token_balances'] ?? [];
                    break;

                case 'bitcoin':
                    $btc = $this->BitcoinService->getAddressBalance($wallet->address);
                    $ethBalance = number_format($btc, 8, '.', '');
                    break;

                case 'bsc':
                    $balance = $this->BscService->getBscMasterBalances();
                    $ethBalance = $balance['bnb_balance'] ?? "0.00000000";
                    $tokenBalances = $balance['token_balances'] ?? [];
                    break;

                case 'tron':
                    $trx = $this->TronService->getTrxBalance($wallet->address);
                    $ethBalance = number_format($trx, 8, '.', '');
                    break;

                case 'litecoin':
                    $ltc = $this->LitecoinService->getAddressBalance($wallet->address);
                    $ethBalance = number_format($ltc, 8, '.', '');
                    break;

                default:
                    // Unsupported blockchains like solana
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Wallet balance fetch failed: ' . $e->getMessage(), ['wallet_id' => $wallet->id]);
        }

        // Clean sensitive data
        $cleanWallet = Arr::except($wallet->toArray(), [
            'private_key', 'mnemonic', 'xpub', 'response'
        ]);

        // Flatten token balances
        foreach ($tokenBalances as $key => $value) {
            $cleanWallet[$key] = $value;
        }

        $cleanWallet['eth_balance'] = $ethBalance;

        return $cleanWallet;
    });

    return response()->json([
        'status' => 'success',
        'message' => 'Master wallet details fetched',
        'data' => [
            'totalWallets' => $totalWallets,
            'wallet' => $walletsWithBalances
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
