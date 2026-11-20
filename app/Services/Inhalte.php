<?php

namespace App\Services;

use App\Models\Dokument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Leert alle Inhalte des Tools (Ressorts, Turniere, Hallen, Vorlagen, Ausgaben, Aufgaben,
 * Wissen, Verlauf). Personen, Einstellungen, Push-Abos und Deploy-Verlauf bleiben erhalten.
 */
class Inhalte
{
    /** Reihenfolge: abhaengige Tabellen zuerst. */
    public const TABELLEN = [
        'notifications', 'verknuepfungen', 'kennzahlen', 'versandprotokoll', 'rundmeldungen', 'aktivitaeten',
        'uebergaben', 'wissen', 'dokumente', 'kontakte', 'erkenntnisse', 'kommentare',
        'aufgaben', 'vorlage_versionen', 'vorlagen', 'ausgaben', 'turniere', 'hallen',
        'ressort_user', 'ressorts',
    ];

    public function __construct(private Sicherung $sicherung) {}

    /** @return array{sicherung: string, geloescht: int} */
    public function leeren(): array
    {
        $name = $this->sicherung->erstelle('vor-leeren');

        $dateien = Dokument::query()->where('typ', 'datei')->whereNotNull('pfad')->pluck('pfad')->all();

        $geloescht = 0;
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use (&$geloescht) {
                foreach (self::TABELLEN as $tabelle) {
                    $geloescht += DB::table($tabelle)->delete();
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        if ($dateien) {
            Storage::disk('local')->delete($dateien);
        }
        Cache::flush();

        return ['sicherung' => $name, 'geloescht' => $geloescht];
    }
}
