<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\VirtualAccount;
use App\Services\WalletManagementService;
use Exception;
use Illuminate\Http\Request;

class WalletManagementController extends Controller
{
    protected $WalletManagementService;
    public function __construct(WalletManagementService $WalletManagementService)
    {
        $this->WalletManagementService = $WalletManagementService;
    }
    public function getVirtualWalletData()
    {
        try {
            $data = $this->WalletManagementService->getVirtualWalletsData();
            return ResponseHelper::success($data);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function freezeWallet($id)
    {
        $virtualAccount = VirtualAccount::find($id);
        if (!$virtualAccount) {
            return ResponseHelper::error('Virtual account not found');
        }
        if ($virtualAccount->frozen == true) {
            $freeze = 'unfreeze';
        } else {
            $freeze = 'freeze';
        }
        $virtualAccount->frozen = !$virtualAccount->frozen;
        $virtualAccount->save();
        return ResponseHelper::success($virtualAccount, 'Wallet ' . $freeze . 'd successfully', 200);
    }
}
