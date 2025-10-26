<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $firebaseNotificationService;
    protected $expoNotificationService;

    /**
     * Inject both Firebase and Expo notification services.
     */
    public function __construct(
        FirebaseNotificationService $firebaseNotificationService,
        ExpoNotificationService $expoNotificationService
    ) {
        $this->firebaseNotificationService = $firebaseNotificationService;
        $this->expoNotificationService = $expoNotificationService;
    }

    /**
     * Send a notification to a specific user by their user ID.
     *
     * This method supports:
     *  - Android → Firebase FCM
     *  - iOS → Expo Push API (APNs)
     *
     * @param int $userId
     * @param string $title
     * @param string $body
     * @return array
     */
    public function sendToUserById(int $userId, string $title, string $body): array
    {
        $user = User::find($userId);
        Log::info("📩 Notification request received", ['userId' => $userId, 'title' => $title, 'body' => $body]);

        if (!$user) {
            Log::warning("⚠️ User not found for userId: $userId");
            return ['status' => 'error', 'message' => 'User not found'];
        }

        $responses = [];

        try {
            // ✅ Send via Firebase (Android)
            if (!empty($user->fcmToken)) {
                Log::info("📲 Sending Firebase notification to userId: $userId, token: $user->fcmToken");
                $firebaseResponse = $this->firebaseNotificationService->sendNotification(
                    $user->fcmToken,
                    $title,
                    $body,
                    (string) $userId
                );
                $responses['firebase'] = $firebaseResponse;
                Log::info("✅ Firebase notification sent successfully", $firebaseResponse);
            }

            // 🍏 Send via Expo (iOS)
            if (!empty($user->expoToken)) {
                Log::info("🍏 Sending Expo notification to userId: $userId, token: $user->expoToken");
                $expoResponse = $this->expoNotificationService->sendNotification(
                    $user->expoToken,
                    $title,
                    $body,
                    ['userId' => (string) $userId]
                );
                $responses['expo'] = $expoResponse;
                Log::info("✅ Expo notification sent successfully", $expoResponse);
            }

            if (empty($responses)) {
                Log::warning("⚠️ No valid notification token found for userId: $userId");
                return ['status' => 'error', 'message' => 'No valid notification token found'];
            }

            return [
                'status' => 'success',
                'message' => 'Notification(s) sent successfully',
                'responses' => $responses
            ];
        } catch (\Exception $e) {
            Log::error("❌ Error sending notification to userId: $userId - " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to send notification',
                'error' => $e->getMessage(),
            ];
        }
    }
}
