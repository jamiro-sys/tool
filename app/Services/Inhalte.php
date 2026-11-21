<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leert alle Inhalte des Tools (Ressorts, Turniere, Hallen, Vorlagen, Ausgaben, Aufgaben,
 * Wissen, Verlauf). Personen, Einstellungen, Push-Abos und Deploy-Verlauf bleiben erhalten.
 */
class Inhalte
{
    /** Reihenfolge: abhaengige Tabellen zuerst. */
    public const TABELLEN = [
        'notifications', 'auswahllisten', 'verknuepfungen', 'kennzahlen', 'kassen', 'einkauf_posten', 'produkte', 'material_einsatz', 'material', 'versandprotokoll', 'rundmeldungen', 'aktivitaeten',
        'uebergaben', 'wissen', 'dokumente', 'ordner', 'kontakte', 'erkenntnisse', 'kommentare',
        'aufgaben', 'vorlage_versionen', 'turnier_vorlage', 'vorlagen', 'ausgaben', 'turniere', 'hallen',
        'ressort_user', 'ressorts',
    ];

    public function __construct(private Sicherung $sicherung) {}

    /** @return array{sicherung: string, geloescht: int} */
    public function leeren(): array
    {
        $name = $this->sicherung->erstelle('vor-leeren');

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

        // Hochgeladene Dateien bleiben liegen, damit die Sicherung von vorhin vollstaendig wiederhergestellt werden kann
        Cache::flush();

        return ['sicherung' => $name, 'geloescht' => $geloescht];
    }
}
