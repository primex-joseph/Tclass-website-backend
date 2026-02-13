<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
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
            subject: 'TCLASS Admission Update - Application Rejected',
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
