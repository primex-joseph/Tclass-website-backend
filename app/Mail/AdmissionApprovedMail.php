<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdmissionApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $fullName,
        public string $studentNumber,
        public string $temporaryPassword,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'TCLASS Admission Approved - Login Credentials',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admission-approved',
            with: [
                'fullName' => $this->fullName,
                'studentNumber' => $this->studentNumber,
                'temporaryPassword' => $this->temporaryPassword,
            ],
        );
    }
}

