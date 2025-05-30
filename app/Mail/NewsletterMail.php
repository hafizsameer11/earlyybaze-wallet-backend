<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $htmlContent;
    public string $subjectLine;

    /**
     * Create a new message instance.
     */
    public function __construct(string $subject, string $htmlContent)
    {
        $this->subjectLine = $subject;
        $this->htmlContent = $htmlContent;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this
            ->subject($this->subjectLine)
            ->html($this->htmlContent);
    }
}
