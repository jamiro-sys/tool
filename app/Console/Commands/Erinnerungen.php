<?php

namespace App\Console\Commands;

use App\Models\Aufgabe;
use App\Models\Einstellung;
use App\Services\Benachrichtiger;
use Illuminate\Console\Command;

class Erinnerungen extends Command
{
    protected $signature = 'ok:erinnerungen';

    protected $description = 'Erinnert an bald fällige und überfällige Aufgaben (einmal pro Tag und Aufgabe).';

    public function handle(Benachrichtiger $benachrichtiger): int
    {
        $tage = (int) Einstellung::wert('erinnerung_tage', 3);
        $aufgaben = Aufgabe::with('verantwortlich', 'ressort')
            ->offen()
            ->whereNotNull('verantwortlich_id')
            ->whereNotNull('faellig_am')
            ->whereDate('faellig_am', '<=', today()->addDays($tage))
            ->where(fn ($q) => $q->whereNull('erinnert_am')->orWhereDate('erinnert_am', '<', today()))
            ->whereHas('ausgabe', fn ($q) => $q->where('status', '!=', 'abgeschlossen'))
            ->get();

        $anzahl = 0;
        foreach ($aufgaben as $a) {
            $ueberfaellig = $a->istUeberfaellig();
            // Bald faellig: nur genau X Tage vorher und am Faelligkeitstag erinnern, ueberfaellig: alle 3 Tage.
            $bisFaellig = (int) today()->diffInDays($a->faellig_am, false);
            if (! $ueberfaellig && ! in_array($bisFaellig, [$tage, 0], true)) {
                continue;
            }
            if ($ueberfaellig && abs($bisFaellig) % 3 !== 1) {
                continue;
            }
            $benachrichtiger->sende(
                $a->verantwortlich,
                $ueberfaellig ? 'ueberfaellig' : 'faellig',
                ($ueberfaellig ? 'Überfällig: ' : 'Bald fällig: ').$a->titel,
                'Fällig '.$a->faelligText().' ('.$a->ressort->name.'). Brauchst du Hilfe? Melde dich im Ressort.',
                route('aufgaben.show', $a)
            );
            $a->forceFill(['erinnert_am' => now()])->saveQuietly();
            $anzahl++;
        }

        // Geliehenes Material: am Vortag und am Tag der Rueckgabe erinnern
        $leihen = \App\Models\MaterialEinsatz::with('material', 'verantwortlich', 'ausgabe', 'quellePerson')
            ->where('zurueck', false)->whereNotNull('verantwortlich_id')->whereNotNull('rueckgabe_bis')
            ->whereDate('rueckgabe_bis', '<=', today()->addDay())->whereDate('rueckgabe_bis', '>=', today())
            ->where(fn ($q) => $q->whereNull('erinnert_am')->orWhereDate('erinnert_am', '<', today()))
            ->get();
        foreach ($leihen as $l) {
            $benachrichtiger->sende(
                $l->verantwortlich,
                'faellig',
                'Rückgabe: '.$l->material->name,
                'Zurückgeben bis '.$l->rueckgabe_bis->locale('de_CH')->isoFormat('dd, D. MMMM').($l->quelle || $l->quelle_user_id ? ' ('.$l->herkunftText().')' : '').'.',
                route('material.ausgabe', $l->ausgabe)
            );
            $l->forceFill(['erinnert_am' => now()])->saveQuietly();
            $anzahl++;
        }

        $this->info("{$anzahl} Erinnerungen versendet.");

        return self::SUCCESS;
    }
}
