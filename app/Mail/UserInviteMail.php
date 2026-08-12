<?php

namespace App\Mail;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class UserInviteMail extends Mailable
{
    public function __construct(
        public User $user,
        public string $plainPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You are invited to OnPoint Call',
        );
    }

    public function content(): Content
    {
        $canAccessAdmin = in_array($this->user->role, [UserRole::Admin, UserRole::Manager], true);

        return new Content(
            view: 'mail.user-invite',
            with: [
                'user' => $this->user,
                'plainPassword' => $this->plainPassword,
                'agentLoginUrl' => url('/agent/login'),
                'adminLoginUrl' => url('/admin'),
                'canAccessAdmin' => $canAccessAdmin,
            ],
        );
    }
}
