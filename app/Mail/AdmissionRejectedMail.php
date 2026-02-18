<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AdmissionRejectedMail extends Mailable
{
    public function __construct(
        public string $fullName,
        public string $reason
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                address: (string) config('mail.from.address'),
                name: 'Tarlac Center for Learning and Skills Success'
            ),
            subject: 'Admission Update - Tarlac Center for Learning and Skills Success',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admission-rejected',
            with: [
                'fullName' => $this->fullName,
                'reason' => $this->reason,
            ]
        );
    }
}
