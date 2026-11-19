<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class Einladung extends Mailable
{
    use Queueable;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Einladung ins OK-Tool');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.einladung', with: ['link' => route('einladung', $this->user->einladung_token)]);
    }
}
