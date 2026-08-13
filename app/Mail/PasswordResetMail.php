<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordResetMail extends Mailable
{
    public function __construct(
        public User $user,
        public string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset your OnPoint Call password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.reset-password',
            with: [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
                'expireMinutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ],
        );
    }
}
