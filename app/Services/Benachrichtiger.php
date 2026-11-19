<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\Hinweis;
use Illuminate\Support\Collection;

/**
 * Zentrale Stelle fuer alle Benachrichtigungen. Beachtet die Einstellungen jeder Person.
 */
class Benachrichtiger
{
    /**
     * @param  User|iterable<User>  $empfaenger
     */
    public function sende(User|iterable $empfaenger, string $ereignis, string $titel, string $text, ?string $url = null, ?User $ausser = null): int
    {
        $liste = $empfaenger instanceof User ? collect([$empfaenger]) : collect($empfaenger);
        $anzahl = 0;

        $liste->filter()->unique('id')
            ->filter(fn (User $u) => $u->aktiv && (! $ausser || $u->id !== $ausser->id))
            ->each(function (User $u) use ($ereignis, $titel, $text, $url, &$anzahl) {
                $kanaele = $u->kanaeleFuer($ereignis);
                if (! $kanaele) {
                    return;
                }
                $u->notify(new Hinweis($ereignis, $titel, $text, $url, $kanaele));
                $anzahl++;
            });

        return $anzahl;
    }

    public static function empfaengerRessort(int $ressortId): Collection
    {
        return User::whereHas('ressorts', fn ($q) => $q->where('ressorts.id', $ressortId))->get();
    }
}
