<?php

namespace App\Listeners;

use App\Models\Versand;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;

class VersandProtokollieren
{
    public function handle(NotificationSent|NotificationFailed $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }
        Versand::create([
            'user_id' => $event->notifiable->id ?? null,
            'kanal' => 'mail',
            'typ' => $event->notification->ereignis ?? class_basename($event->notification),
            'status' => $event instanceof NotificationSent ? 'ok' : 'fehler',
            'fehler' => $event instanceof NotificationFailed ? mb_substr(json_encode($event->data), 0, 250) : null,
        ]);
    }
}
