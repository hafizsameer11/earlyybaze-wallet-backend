<?php

namespace App\Jobs;

use App\Mail\NewsletterMail;
use App\Models\Newsletter;
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
    protected ?int $newsletterId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $userIds, string $subject, string $htmlContent, ?int $newsletterId = null)
    {
        $this->userIds = $userIds;
        $this->subject = $subject;
        $this->htmlContent = $htmlContent;
        $this->newsletterId = $newsletterId;
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

            if ($this->newsletterId) {
                Newsletter::find($this->newsletterId)?->users()->updateExistingPivot($user->id, [
                    'sent_at' => now(),
                ]);
            }

            usleep(200000);
        }

        if ($this->newsletterId) {
            Newsletter::where('id', $this->newsletterId)->update(['status' => 'completed']);
        }
    }
}
