<?php

namespace App\Http\Controllers\V3;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class V3UserController extends Controller
{
    public function __construct(private UserAccountService $userAccountService) {}

    public function balance()
    {
        try {
            $payload = $this->userAccountService->getBalance();
            $userBalance = $payload['userBalance'] ?? null;

            return ResponseHelper::success($payload, 'User balance fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /** Register Expo or FCM push token for the authenticated user. */
    public function setPushToken(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'provider' => 'required|in:fcm,expo',
        ]);

        $user = User::findOrFail(Auth::id());

        if ($validated['provider'] === 'expo') {
            $user->expoToken = $validated['token'];
        } else {
            $user->fcmToken = $validated['token'];
        }

        $user->save();

        return ResponseHelper::success([
            'provider' => $validated['provider'],
        ], 'Push token saved', 200);
    }

}
