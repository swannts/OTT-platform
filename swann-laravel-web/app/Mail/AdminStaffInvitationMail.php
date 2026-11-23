<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminStaffInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $invitationUrl)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Invitation to Noor Islamic Cinema Admin');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-staff-invitation');
    }
}
