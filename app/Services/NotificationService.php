<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $firebaseNotificationService;

    public function __construct(FirebaseNotificationService $firebaseNotificationService)
    {
        $this->firebaseNotificationService = $firebaseNotificationService;
    }

    /**
     * Send a notification to a specific user by their user ID.
     *
     * @param int $userId
     * @param string $title
     * @param string $body
     * @return array
     */
    public function sendToUserById(int $userId, string $title, string $body): array
    {
        $user = User::find($userId);
        Log::info("data received",[$userId,$title,$body]);  
        if (!$user || !$user->fcmToken) {
            Log::warning("User or FCM token not found for userId: $userId");
            return ['status' => 'error', 'message' => 'User or FCM token not found'];
        }
        //conver userId to string
        $stringUserId = (string) $userId;
        try {
            $response = $this->firebaseNotificationService->sendNotification(
                $user->fcmToken,
                $title,
                $body,
                $stringUserId // Pass userId directly
            );

            Log::info("Notification sent to userId: $userId", $response);

            return ['status' => 'success', 'message' => 'Notification sent successfully', 'response' => $response];
        } catch (\Exception $e) {
            Log::error("Error sending notification to userId: $userId - " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Failed to send notification', 'error' => $e->getMessage()];
        }
    }
}
