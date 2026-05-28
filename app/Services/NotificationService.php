<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(
        protected FirebaseNotificationService $firebaseNotificationService,
        protected ExpoNotificationService $expoNotificationService,
    ) {}

    /**
     * Persist in-app notification and send Expo push (primary) + FCM when available.
     */
    public function notifyUser(
        int $userId,
        string $title,
        string $message,
        string $type = 'activity',
        string $status = 'unread',
    ): void {
        try {
            UserNotification::create([
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'status' => $status,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to store user notification', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        $this->sendToUserById($userId, $title, $message);
    }

    /**
     * Send push via Expo (primary) and Firebase FCM (Android fallback).
     */
    public function sendToUserById(int $userId, string $title, string $body): array
    {
        $user = User::find($userId);
        Log::info('Notification request', ['userId' => $userId, 'title' => $title]);

        if (! $user) {
            Log::warning("User not found for userId: $userId");

            return ['status' => 'error', 'message' => 'User not found'];
        }

        $responses = [];

        try {
            if (! empty($user->expoToken)) {
                Log::info("Sending Expo notification to userId: $userId");
                $expoResponse = $this->expoNotificationService->sendNotification(
                    $user->expoToken,
                    $title,
                    $body,
                    ['userId' => (string) $userId]
                );
                $responses['expo'] = $expoResponse;
            }

            if (! empty($user->fcmToken)) {
                Log::info("Sending Firebase notification to userId: $userId");
                $firebaseResponse = $this->firebaseNotificationService->sendNotification(
                    $user->fcmToken,
                    $title,
                    $body,
                    (string) $userId
                );
                $responses['firebase'] = $firebaseResponse;
            }

            if (empty($responses)) {
                Log::warning("No push token for userId: $userId (in-app record may still exist)");

                return ['status' => 'skipped', 'message' => 'No push token; in-app notification saved'];
            }

            return [
                'status' => 'success',
                'message' => 'Notification(s) sent successfully',
                'responses' => $responses,
            ];
        } catch (\Exception $e) {
            Log::error("Error sending notification to userId: $userId - ".$e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Failed to send notification',
                'error' => $e->getMessage(),
            ];
        }
    }
}
