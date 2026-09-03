<?php

namespace App\Mail;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class UserInviteMail extends Mailable
{
    public bool $canAccessAdmin;

    public string $loginUrl;

    public function __construct(
        public User $user,
        public string $plainPassword,
    ) {
        $role = $this->user->role instanceof UserRole
            ? $this->user->role
            : UserRole::coerce((string) $this->user->role);

        $this->canAccessAdmin = $role->canAccessAdmin();
        $this->loginUrl = url('/');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You are invited to OnPoint Call',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.user-invite',
        );
    }
}
