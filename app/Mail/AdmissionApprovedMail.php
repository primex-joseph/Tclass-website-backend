<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdmissionApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $fullName,
        public string $studentNumber,
        public ?string $temporaryPassword = null,
        public ?int $score = null,
        public ?int $total = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                address: (string) config('mail.from.address'),
                name: 'Tarlac Center for Learning and Skills Success'
            ),
            subject: 'Admission Approved - Tarlac Center for Learning and Skills Success',
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
                'score' => $this->score,
                'total' => $this->total,
            ],
        );
    }
}
