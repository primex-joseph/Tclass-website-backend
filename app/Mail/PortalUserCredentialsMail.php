<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalUserCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $fullName,
        public string $email,
        public string $role,
        public string $password
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                address: (string) config('mail.from.address'),
                name: 'Tarlac Center for Learning and Skills Success'
            ),
            subject: 'Your TCLASS Portal Login Credentials'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal-user-credentials',
            with: [
                'fullName' => $this->fullName,
                'email' => $this->email,
                'role' => ucfirst($this->role),
                'password' => $this->password,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
