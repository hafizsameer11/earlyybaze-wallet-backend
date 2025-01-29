<?php

namespace App\Http\Controllers\Wallet;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\PinRequest;
use App\Services\UserService;
use Exception;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected $userService;
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }
    public function setpin(PinRequest $request)
    {
        try {
            $user = $this->userService->setPin($request->email, $request->pin);
            return ResponseHelper::success($user, 'Pin set successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }

    }
    public function verifyPin(Request $request)
    {
        try {
            $user = $this->userService->verifyPin($request->pin);
            return ResponseHelper::success($user, 'Pin verified successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
