<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

final class DocumentGovernanceMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{title: string, message: string}>  $items
     */
    public function __construct(
        public readonly string $mailSubject,
        public readonly string $heading,
        public readonly string $summary,
        public readonly string $workspaceName,
        public readonly string $actionLabel,
        public readonly string $actionUrl,
        public readonly string $preferenceUrl,
        public readonly array $items,
        public readonly string $idempotencyKey,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function headers(): Headers
    {
        return new Headers(text: ['X-Dolved-Idempotency-Key' => $this->idempotencyKey]);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.governance.html',
            text: 'mail.governance.text',
        );
    }
}
