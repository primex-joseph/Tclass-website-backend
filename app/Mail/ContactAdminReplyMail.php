<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactAdminReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $subjectLine,
        public string $replyBody,
        public string $originalMessage,
        public string $repliedBy
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                address: (string) config('mail.from.address'),
                name: 'Tarlac Center for Learning and Skills Success'
            ),
            subject: $this->subjectLine
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-admin-reply',
            with: [
                'firstName' => $this->firstName,
                'lastName' => $this->lastName,
                'replyBody' => $this->replyBody,
                'originalMessage' => $this->originalMessage,
                'repliedBy' => $this->repliedBy,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
