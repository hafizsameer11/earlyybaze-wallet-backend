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
        return response()->json(['message' => 'Admin transfer created successfully', 'data' => $AdminTransfer]);
    }
    public function getAdminTransfer()
    {
        $adminTransfers = AdminTransfer::all();
        return response()->json($adminTransfers);
    }
    public function setIndividualTransfer($id, Request $request)
    {
        $asset = ReceivedAsset::find($id);
        if (!$asset) {
            return response()->json(['message' => 'Asset not found'], 404);
        }
        $asset->transfer_address = $request->transfer_address;
        $asset->save();
        return response()->json(['message' => 'Transfer address updated successfully', 'data' => $asset]);
    }
}
