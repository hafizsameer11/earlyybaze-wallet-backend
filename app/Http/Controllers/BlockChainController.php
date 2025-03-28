<?php

namespace App\Http\Controllers;

use App\Helpers\BlockChainHelper;
use App\Models\WalletCurrency;

use Illuminate\Http\Request;

class BlockChainController extends Controller
{
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
