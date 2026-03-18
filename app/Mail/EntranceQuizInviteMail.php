<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EntranceQuizInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $fullName,
        public string $course,
        public string $subjectLine,
        public string $introMessage,
        public string $quizTitle,
        public string $quizLink,
        public int $durationMinutes,
        public ?string $expiresAt = null,
        public ?string $temporaryPassword = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                address: (string) config('mail.from.address'),
                name: 'Tarlac Center for Learning and Skills Success'
            ),
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.entrance-quiz-invite',
            with: [
                'fullName' => $this->fullName,
                'course' => $this->course,
                'introMessage' => $this->introMessage,
                'quizTitle' => $this->quizTitle,
                'quizLink' => $this->quizLink,
                'durationMinutes' => $this->durationMinutes,
                'expiresAt' => $this->expiresAt,
                'temporaryPassword' => $this->temporaryPassword,
            ],
        );
    }
}
