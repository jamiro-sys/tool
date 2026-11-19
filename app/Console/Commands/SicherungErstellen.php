<?php

namespace App\Console\Commands;

use App\Models\Einstellung;
use App\Services\Sicherung;
use Illuminate\Console\Command;

class SicherungErstellen extends Command
{
    protected $signature = 'ok:sicherung {anlass=automatisch}';

    protected $description = 'Erstellt eine Datenbank-Sicherung und räumt alte Sicherungen auf.';

    public function handle(Sicherung $sicherung): int
    {
        $anlass = preg_replace('/[^a-z0-9\-]/', '', strtolower($this->argument('anlass'))) ?: 'automatisch';
        $name = $sicherung->erstelle($anlass);
        $geloescht = $sicherung->aufraeumen((int) Einstellung::wert('backup_aufbewahrung', 14));
        $this->info("Sicherung {$name} erstellt, {$geloescht} alte entfernt.");

        return self::SUCCESS;
    }
}
