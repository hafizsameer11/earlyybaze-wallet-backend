<?php

namespace App\Http\Controllers;

use App\Helpers\BlockChainHelper;
use App\Models\MasterWallet;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;
use App\Services\BscService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockChainController extends Controller
{
    protected $bscService;
    public function __construct(BscService $bscService)
    {
        $this->bscService = $bscService;
    }
    public function manualTransformToMasterWalts(Request $request)
    {
        try {
            $userId = $request->user_id;
            $currency = $request->currency;
            $blockchain = $request->blockchain;
            $virtualAccount = VirtualAccount::where('currency', $currency)->where('blockchain', $blockchain)->where('user_id', $userId)->with('user')->first();
            $amount = $request->amount;
            $masterWallet = MasterWallet::where('blockchain', $blockchain)->first();
            $masterWalletAddress = $masterWallet->address;
            $beforebalanceOfMasterWallet = BlockChainHelper::checkAddressBalance($masterWalletAddress, $blockchain, $masterWallet->contract_address);
            if ($blockchain == 'bsc' || $blockchain=="BSC") {
                $transferToMasterWallet = $this->bscService->transferToMasterWallet($virtualAccount, $amount);
            } else {
                $transferToMasterWallet = BlockChainHelper::dispatchTransferToMasterWallet($virtualAccount, $amount);
            }

            $afterbalanceOfMasterWallet = BlockChainHelper::checkAddressBalance($masterWalletAddress, $blockchain, $masterWallet->contract_address);
            return response()->json([
                'beforebalanceOfMasterWallet' => $beforebalanceOfMasterWallet,
                'transferToMasterWallet' => $transferToMasterWallet,
                'afterbalanceOfMasterWallet' => $afterbalanceOfMasterWallet,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
    public function checkAddressBalance(Request $request)
    {
        $address = $request->address;
        $blockchain = $request->blockchain;
        $walletCurrency = WalletCurrency::where('blockchain', $blockchain)->first();
        $tokenContract = $walletCurrency->contract_address;
        // $tokenContract = $request->tokenContract;
        $balance = BlockChainHelper::checkAddressBalance($address, $blockchain, $tokenContract);
        return response()->json(['balance' => $balance]);
    }
    public function sendFromVirtualToExternalTron(Request $request)
    {
        $accountId = $request->accountId;
        $currency = $request->currency;
        $toAddress = $request->toAddress;
        $amount = $request->amount;
        $sendFromVirtualToExternalTron = BlockChainHelper::sendFromVirtualToExternalTron($accountId, $currency, $toAddress, $amount);
        return response()->json(['sendFromVirtualToExternalTron' => $sendFromVirtualToExternalTron]);
    }
    public function transferToExternalAddress(Request $request)
    {
        $request->validate([
            'blockchain' => 'required|string',
            'currency' => 'required|string',
            'to_address' => 'required|string',
            'amount' => 'required|numeric|min:0.00000001',
        ]);

        $user = Auth::user();

        try {
            $result = BlockchainHelper::sendToExternalAddress(
                $user,
                $request->blockchain,
                $request->currency,
                $request->to_address,
                $request->amount
            );

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal initiated successfully.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
