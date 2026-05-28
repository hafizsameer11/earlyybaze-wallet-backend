<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPushNotificationToAudience implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $title,
        protected string $body,
        protected string $audience = 'all',
        protected array $userIds = [],
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        $query = User::query();

        switch ($this->audience) {
            case 'verified':
                $query->where('otp_verified', true);
                break;
            case 'unverified':
                $query->where('otp_verified', false);
                break;
            case 'selected':
                if (empty($this->userIds)) {
                    return;
                }
                $query->whereIn('id', $this->userIds);
                break;
            default:
                break;
        }

        $users = $query->where(function ($q) {
            $q->whereNotNull('fcmToken')->orWhereNotNull('expoToken');
        })->get();

        foreach ($users as $user) {
            $notificationService->notifyUser($user->id, $this->title, $this->body, 'announcement');
            usleep(100000);
        }
    }
}
