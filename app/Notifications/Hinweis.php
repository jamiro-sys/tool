<?php

namespace App\Notifications;

use App\Channels\WebPushKanal;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Eine Benachrichtigung fuer alle Kanaele. Die App-Glocke erhaelt sie sofort,
 * E-Mail und Push werden in der Ruhezeit zurueckgehalten.
 */
class Hinweis extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $ereignis,
        public string $titel,
        public string $text,
        public ?string $url = null,
        public array $kanaele = ['app'],
    ) {}

    public function via(User $notifiable): array
    {
        $via = [];
        if (in_array('app', $this->kanaele, true) || $notifiable->zusammenfassung) {
            $via[] = 'database';
        }
        if (in_array('mail', $this->kanaele, true) && ! $notifiable->zusammenfassung) {
            $via[] = 'mail';
        }
        if (in_array('push', $this->kanaele, true) && \App\Models\Einstellung::wert('funktion_push', true)) {
            $via[] = WebPushKanal::class;
        }

        return $via;
    }

    /** Zurueckhalten in der Ruhezeit (nur Mail und Push). */
    public function withDelay(User $notifiable): array
    {
        if (! $notifiable->inRuhezeit()) {
            return [];
        }
        [$h, $m] = array_map('intval', explode(':', $notifiable->ruhe_bis));
        $ende = now()->setTime($h, $m);
        if ($ende->isPast()) {
            $ende->addDay();
        }

        return ['mail' => $ende, WebPushKanal::class => $ende];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->titel)
            ->greeting('Hallo '.strtok($notifiable->name, ' '))
            ->line($this->text);
        if ($this->url) {
            $mail->action('Im OK-Tool öffnen', $this->url);
        }

        return $mail->salutation('Sportliche Grüsse, dein OK-Tool');
    }

    public function toArray(User $notifiable): array
    {
        return [
            'ereignis' => $this->ereignis,
            'titel' => $this->titel,
            'text' => $this->text,
            'url' => $this->url,
        ];
    }

    public function toWebPush(User $notifiable): array
    {
        return [
            'title' => $this->titel,
            'body' => $this->text,
            'url' => $this->url ?? url('/'),
            'tag' => $this->ereignis,
        ];
    }
}
