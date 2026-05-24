<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\AdminTransfer;
use App\Models\ReceivedAsset;
use App\Models\RejectedDepositWebhook;
use App\Support\AllowedFungibleContracts;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function getAvaialbleAsset()
    {
        $assets = ReceivedAsset::with('user')->latest()->get();
        return $assets;
    }

    /**
     * Rejected / fake deposit webhooks (not credited to user balance).
     */
    public function getRejectedDeposits(Request $request)
    {
        $q = RejectedDepositWebhook::query()->with('user:id,email,name');

        if ($request->filled('rejection_reason')) {
            $q->where('rejection_reason', $request->string('rejection_reason'));
        }
        if ($request->filled('tx_id')) {
            $q->where('tx_id', $request->string('tx_id'));
        }
        if ($request->filled('to_address')) {
            $q->where('to_address', 'like', '%'.$request->string('to_address').'%');
        }
        if ($request->filled('contract_address')) {
            $q->whereRaw('LOWER(contract_address) = ?', [strtolower($request->string('contract_address'))]);
        }
        if ($request->filled('user_id')) {
            $q->where('user_id', (int) $request->input('user_id'));
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 25)));
        $paginated = $q->latest('id')->paginate($perPage);

        $paginated->getCollection()->transform(function (RejectedDepositWebhook $row) {
            $row->rejection_reason_label = AllowedFungibleContracts::rejectionReasonLabel($row->rejection_reason);

            return $row;
        });

        return ResponseHelper::success($paginated, 'Rejected deposit webhooks fetched', 200);
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
