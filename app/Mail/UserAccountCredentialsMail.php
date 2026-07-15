<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserAccountCredentialsMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly string $plainPassword
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Account Login Credentials',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.users.account_credentials',
        );
    }
}
