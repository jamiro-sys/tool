<?php

namespace App\Support;

/** Titelvorschlag aus einem Dateinamen (gleiche Regeln wie titelAusDateiname in app.js). */
class Dateiname
{
    public static function titel(string $name): string
    {
        $t = preg_replace('/\.[a-z0-9]{1,5}$/i', '', $name);
        $t = preg_replace(['/[_.]+/', '/\s+-+\s+|--+/'], ' ', $t);
        $t = preg_replace(['/\s*\(\d+\)\s*$/', '/\s+(kopie|copy)(\s*\d+)?$/i'], '', $t);
        $t = trim(preg_replace('/\s+/', ' ', $t));

        return $t === '' ? $name : mb_strtoupper(mb_substr($t, 0, 1)).mb_substr($t, 1);
    }
}
