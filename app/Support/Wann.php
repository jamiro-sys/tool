<?php

namespace App\Support;

use App\Models\Ausgabe;
use Carbon\Carbon;

/**
 * Faelligkeit als Abstand zum Turnier.
 * tage: positiv = vorher, 0 = am Tag, negativ = nachher. bezug: "beginn" (erster Turniertag) oder "ende" (letzter Turniertag).
 */
class Wann
{
    public const BEZUG = ['beginn', 'ende'];

    public static function datum(Carbon $von, ?Carbon $bis, int $tage, string $bezug = 'beginn'): Carbon
    {
        $basis = $bezug === 'ende' ? ($bis ?? $von) : $von;

        return $basis->copy()->startOfDay()->subDays($tage);
    }

    public static function fuer(Ausgabe $ausgabe, int $tage, string $bezug = 'beginn'): Carbon
    {
        return self::datum($ausgabe->datum_von, $ausgabe->datum_bis, $tage, $bezug);
    }

    /** Abstand eines Datums zum Turnierbeginn (positiv = vorher). */
    public static function ausDatum(Ausgabe $ausgabe, Carbon $datum): int
    {
        return (int) $datum->copy()->startOfDay()->diffInDays($ausgabe->datum_von->copy()->startOfDay(), false);
    }

    /** Kurz fuer Listen: "2 Wochen vorher", "am Turniertag", "am letzten Turniertag", "3 Tage nachher". */
    public static function kurz(?int $tage, ?string $bezug = 'beginn'): string
    {
        if ($tage === null) {
            return '';
        }
        $ende = $bezug === 'ende';
        if ($tage === 0) {
            return $ende ? 'am letzten Turniertag' : 'am Turniertag';
        }

        return $tage > 0
            ? self::dauer($tage).($ende ? ' vor Turnierende' : ' vorher')
            : self::dauer(-$tage).($ende ? ' nachher' : ' nach Turnierbeginn');
    }

    /** Ausfuehrlich: "2 Wochen vor Turnierbeginn", "am ersten Turniertag", "3 Tage nach Turnierende". */
    public static function text(?int $tage, ?string $bezug = 'beginn'): string
    {
        if ($tage === null) {
            return '';
        }
        $ende = $bezug === 'ende';
        if ($tage === 0) {
            return $ende ? 'am letzten Turniertag' : 'am ersten Turniertag';
        }

        return self::dauer(abs($tage)).($tage > 0 ? ' vor Turnier' : ' nach Turnier').($ende ? 'ende' : 'beginn');
    }

    public static function dauer(int $t): string
    {
        if ($t % 30 === 0) {
            return ($t / 30).' '.($t === 30 ? 'Monat' : 'Monate');
        }
        if ($t % 7 === 0 && $t >= 14) {
            return ($t / 7).' Wochen';
        }

        return $t.' '.($t === 1 ? 'Tag' : 'Tage');
    }
}
