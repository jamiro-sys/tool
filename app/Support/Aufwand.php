<?php

namespace App\Support;

/** Aufwand wird als Stunden (Dezimal) gespeichert und als Stunden und Minuten angezeigt. */
class Aufwand
{
    public static function text(?float $stunden, string $leer = ''): string
    {
        if ($stunden === null) {
            return $leer;
        }
        $minuten = (int) round($stunden * 60);
        $h = intdiv($minuten, 60);
        $m = $minuten % 60;

        return match (true) {
            $minuten === 0 => '0 Min.',
            $h === 0 => $m.' Min.',
            $m === 0 => $h.' Std.',
            default => $h.' Std. '.$m.' Min.',
        };
    }
}
