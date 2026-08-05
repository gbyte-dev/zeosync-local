<?php

namespace App\Mail;

use App\Models\ContactInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactThankYouMail extends Mailable
{
    use Queueable, SerializesModels;

    public ContactInquiry $contact;

    public function __construct(ContactInquiry $contact)
    {
        $this->contact = $contact;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thanks for contacting Zeosync',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-thank-you',
            with: [
                'contact' => $this->contact,
            ],
        );
    }
}
