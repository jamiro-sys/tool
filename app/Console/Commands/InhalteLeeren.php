<?php

namespace App\Console\Commands;

use App\Models\Aktivitaet;
use App\Services\Inhalte;
use Illuminate\Console\Command;

class InhalteLeeren extends Command
{
    protected $signature = 'ok:leeren {--force : Ohne Rueckfrage}';

    protected $description = 'Leert alle Inhalte (Ressorts, Turniere, Hallen, Vorlagen, Ausgaben ...). Personen und Einstellungen bleiben. Vorher wird gesichert.';

    public function handle(Inhalte $inhalte): int
    {
        if (! $this->option('force') && ! $this->confirm('Alle Inhalte loeschen? Personen und Einstellungen bleiben, vorher wird gesichert.')) {
            return self::FAILURE;
        }

        $ergebnis = $inhalte->leeren();
        Aktivitaet::protokolliere('daten.leeren', 'Alle Inhalte geleert (Sicherung '.$ergebnis['sicherung'].')');
        $this->info("{$ergebnis['geloescht']} Eintraege geloescht. Sicherung: {$ergebnis['sicherung']}");

        return self::SUCCESS;
    }
}
