<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use App\Helpers\ResponseHelper;

class UserDeviceController extends Controller
{
    public function index(Request $request)
    {
        try {
            $devices = UserDevice::where('user_id', auth()->id())
                ->latest()
                ->get();

            return ResponseHelper::success($devices, 'User devices retrieved successfully');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
}
