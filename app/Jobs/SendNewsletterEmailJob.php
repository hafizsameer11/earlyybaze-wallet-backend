<?php

namespace App\Jobs;

use App\Mail\NewsletterMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNewsletterEmailJob implements ShouldQueue
{
     use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $userIds;
    protected string $subject;
    protected string $htmlContent;

    /**
     * Create a new job instance.
     */
    public function __construct(array $userIds, string $subject, string $htmlContent)
    {
        $this->userIds = $userIds;
        $this->subject = $subject;
        $this->htmlContent = $htmlContent;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $users = User::whereIn('id', $this->userIds)->get();

        foreach ($users as $user) {
            Mail::to($user->email)->send(
                new NewsletterMail($this->subject, $this->htmlContent)
            );
            usleep(200000); // optional delay between sends (200ms)
        }
    }
}
