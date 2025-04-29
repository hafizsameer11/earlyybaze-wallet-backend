<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminTransfer;
use App\Models\ReceivedAsset;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function getAvaialbleAsset()
    {
        $assets = ReceivedAsset::where('status', 'inWallet')->get();
        return $assets;
    }
    public function setAdminTransfer(Request $request)
    {
        $blockchain = $request->input('blockchain');
        $currency = $request->input('currency');
        $address = $request->input('address');
        $forAll = $request->input('forAll');
        $data = [
            'blockchain' => $blockchain,
            'currency' => $currency,
            'address' => $address,
            'forAll' => $forAll
        ];
        $AdminTransfer = new AdminTransfer();
        $AdminTransfer->blockchain = $data['blockchain'];
        $AdminTransfer->currency = $data['currency'];
        $AdminTransfer->address = $data['address'];
        $AdminTransfer->forAll = $data['forAll'];
        $AdminTransfer->save();
        return response()->json(['message' => 'Admin transfer created successfully']);
    }
}
