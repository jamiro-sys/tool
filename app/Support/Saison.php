<?php

namespace App\Support;

use App\Models\Ausgabe;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Eine Saison (Vereinsjahr) umfasst die Turniere von Juli bis Juni, z. B. Dezember 2027 und Februar 2028 = "2027/28".
 * Gespeichert wird sie als Text in ausgaben.saison.
 */
class Saison
{
    public static function fuer(\DateTimeInterface $datum): string
    {
        $d = Carbon::instance($datum);
        $start = $d->month >= 7 ? $d->year : $d->year - 1;

        return $start.'/'.substr((string) ($start + 1), -2);
    }

    /** Erstes Jahr der Saison ("2027/28" -> 2027), null bei unbekanntem Format. */
    public static function startjahr(string $saison): ?int
    {
        return preg_match('/^(\d{4})\/\d{2}$/', trim($saison), $m) ? (int) $m[1] : null;
    }

    public static function naechste(string $saison): string
    {
        $start = self::startjahr($saison) ?? (int) today()->year;

        return ($start + 1).'/'.substr((string) ($start + 2), -2);
    }

    /** Vorschlag fuer die naechste zu planende Saison: nach der neuesten vorhandenen, sonst die laufende. */
    public static function vorschlag(): string
    {
        $neueste = Ausgabe::orderByDesc('datum_von')->value('saison');

        return $neueste && self::startjahr($neueste) ? self::naechste($neueste) : self::fuer(today());
    }

    /** Liegt das Datum in dieser Saison? */
    public static function enthaelt(string $saison, \DateTimeInterface $datum): bool
    {
        return self::fuer($datum) === trim($saison);
    }

    /** @return Collection<int, Ausgabe> Turniere der Saison nach Datum */
    public static function turniere(string $saison): Collection
    {
        return Ausgabe::where('saison', $saison)->orderBy('datum_von')->get();
    }
}
