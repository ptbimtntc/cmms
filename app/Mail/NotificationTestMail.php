<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NotificationTestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Notification Test: '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.notification-test');
    }
}
