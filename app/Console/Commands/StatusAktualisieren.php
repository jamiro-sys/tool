<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AusgabePlaner;
use App\Services\Benachrichtiger;
use Illuminate\Console\Command;

class StatusAktualisieren extends Command
{
    protected $signature = 'ok:status';

    protected $description = 'Setzt den Status der Ausgaben anhand des Turnierdatums.';

    public function handle(AusgabePlaner $planer, Benachrichtiger $benachrichtiger): int
    {
        foreach ($planer->statusAktualisieren() as [$ausgabe, $neu]) {
            $this->info($ausgabe->titel.' => '.$neu);
            if ($neu === 'rueckblick') {
                $benachrichtiger->sende(User::where('aktiv', true)->get(), 'ausgabe', 'Rückblick: '.$ausgabe->titel,
                    'Danke für euren Einsatz! Haltet jetzt fest, was gut lief und was wir nächstes Mal anders machen.',
                    route('ausgaben.rueckblick', $ausgabe));
            }
        }

        return self::SUCCESS;
    }
}
