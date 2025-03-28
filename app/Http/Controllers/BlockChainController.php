<?php

namespace App\Http\Controllers;

use App\Helpers\BlockChainHelper;
use App\Models\MasterWallet;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockChainController extends Controller
{
    public function manualTransformToMasterWalts(Request $request)
    {
      $userId=$request->user_id;
        $currency = $request->currency;
        $blockchain = $request->blockchain;
        $virtualAccount = VirtualAccount::where('currency', $currency)->where('blockchain', $blockchain)->where('user_id', $userId)->with('user')->first();
        $amount = $request->amount;
        $masterWallet = MasterWallet::where('blockchain', $blockchain)->first();
        $masterWalletAddress = $masterWallet->address;
        $beforebalanceOfMasterWallet = BlockChainHelper::checkAddressBalance($masterWalletAddress, $blockchain, $masterWallet->contract_address);
        $transferToMasterWallet = BlockChainHelper::transferToMasterWallet($virtualAccount, $amount);
        $afterbalanceOfMasterWallet = BlockChainHelper::checkAddressBalance($masterWalletAddress, $blockchain, $masterWallet->contract_address);
        return response()->json([
            'beforebalanceOfMasterWallet' => $beforebalanceOfMasterWallet,
            'transferToMasterWallet' => $transferToMasterWallet,
            'afterbalanceOfMasterWallet' => $afterbalanceOfMasterWallet,
        ]);
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
}
