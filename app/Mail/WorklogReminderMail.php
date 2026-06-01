<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorklogReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $displayName,
        public array $missingDays,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reminder: Please fill in your missing worklogs',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.worklog-reminder',
        );
    }
}
