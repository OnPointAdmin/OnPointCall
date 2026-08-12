<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailTestCommand extends Command
{
    protected $signature = 'mail:test {email : Address to send a test message to}';

    protected $description = 'Send a one-line test email through the configured mailer';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        Mail::raw(
            'If you can read this, On Point Call mail is working ('.config('mail.default').').',
            function ($message) use ($email): void {
                $message->to($email)->subject('On Point Call mail test');
            },
        );

        $this->info("Test email sent to {$email} via ".config('mail.default').'.');

        return self::SUCCESS;
    }
}
