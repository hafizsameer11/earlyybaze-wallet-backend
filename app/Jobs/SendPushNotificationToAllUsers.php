<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPushNotificationToAllUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */

    protected string $title;
    protected string $body;
      public function __construct(string $title, string $body)
    {
        $this->title = $title;
        $this->body = $body;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService)
    {
        // Fetch all users with FCM token at once
        $users = User::whereNotNull('fcmToken')->take(1)->get();
        $users=User::where('email','b@gmail.com')->get();
        foreach ($users as $user) {
            $notificationService->sendToUserById($user->id, $this->title, $this->body);
            usleep(200000); // optional: 200ms delay to avoid flooding
        }
    }
}
