<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EntranceExamScheduleMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $fullName,
        public string $course,
        public string $subjectLine,
        public string $introMessage,
        public string $examDate,
        public string $examTime,
        public string $examDay,
        public string $location,
        public string $thingsToBring,
        public ?string $attireNote = null,
        public ?string $additionalNote = null,
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
            view: 'emails.entrance-exam-schedule',
            with: [
                'fullName' => $this->fullName,
                'course' => $this->course,
                'introMessage' => $this->introMessage,
                'examDate' => $this->examDate,
                'examTime' => $this->examTime,
                'examDay' => $this->examDay,
                'location' => $this->location,
                'thingsToBring' => $this->thingsToBring,
                'attireNote' => $this->attireNote,
                'additionalNote' => $this->additionalNote,
            ],
        );
    }
}
