<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DashboardDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{filename: string, content: string, mime: string}>  $fileAttachments
     */
    public function __construct(
        public string $digestSubject,
        public string $htmlBody,
        public array $fileAttachments = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->digestSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody,
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (array $file): Attachment => Attachment::fromData(
                fn (): string => $file['content'],
                $file['filename'],
            )->withMime($file['mime']),
            $this->fileAttachments,
        );
    }
}
