<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

class Zusammenfassung extends Mailable
{
    public function __construct(public User $user, public string $art, public Collection $meldungen, public Collection $aufgaben) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->art === 'woche' ? 'Deine Woche im OK' : 'Deine Tageszusammenfassung');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.zusammenfassung');
    }
}
