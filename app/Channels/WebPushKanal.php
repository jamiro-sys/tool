<?php

namespace App\Channels;

use App\Models\User;
use App\Models\Versand;
use Illuminate\Notifications\Notification;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushKanal
{
    public static function konfiguriert(): bool
    {
        // Oeffentlicher Schluessel: base64url, 65 Bytes (87 Zeichen ohne Auffuellung)
        $k = (string) config('services.webpush.public_key');
        $bytes = base64_decode(strtr($k, '-_', '+/').str_repeat('=', (4 - strlen($k) % 4) % 4), true);

        return (bool) preg_match('/^[A-Za-z0-9_-]{86,88}$/', $k) && $bytes !== false && strlen($bytes) === 65
            && filled(config('services.webpush.private_key'));
    }

    public function send(User $notifiable, Notification $notification): void
    {
        if (! self::konfiguriert() || ! method_exists($notification, 'toWebPush')) {
            return;
        }
        $abos = $notifiable->pushAbos;
        if ($abos->isEmpty()) {
            return;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]]);

        $nutzlast = json_encode($notification->toWebPush($notifiable));
        foreach ($abos as $abo) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $abo->endpoint,
                'publicKey' => $abo->public_key,
                'authToken' => $abo->auth_token,
            ]), $nutzlast);
        }

        foreach ($webPush->flush() as $bericht) {
            $ok = $bericht->isSuccess();
            if (! $ok && $bericht->isSubscriptionExpired()) {
                $notifiable->pushAbos()->where('endpoint', $bericht->getEndpoint())->delete();
            }
            Versand::create([
                'user_id' => $notifiable->id,
                'kanal' => 'push',
                'typ' => $notification->ereignis ?? class_basename($notification),
                'status' => $ok ? 'ok' : 'fehler',
                'fehler' => $ok ? null : mb_substr((string) $bericht->getReason(), 0, 250),
            ]);
        }
    }
}
