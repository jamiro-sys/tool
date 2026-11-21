<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Datenbank-Sicherung als komprimiertes JSON. Funktioniert mit MariaDB und SQLite
 * und braucht kein mysqldump (auf Webhosting oft nicht verfuegbar).
 */
class Sicherung
{
    public const ORDNER = 'backups';

    /** Tabellen, die nicht gesichert werden muessen. */
    protected array $ausnehmen = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'migrations'];

    public function tabellen(): array
    {
        // Nur die Tabellen dieser Datenbank. Ohne Angabe liefert MySQL alle Datenbanken,
        // auf die der Benutzer Zugriff hat (bei Netcup z. B. auch die der Hauptseite).
        $alle = collect(Schema::getTables(Schema::getCurrentSchemaListing()))->pluck('name')
            ->reject(fn ($t) => str_starts_with($t, 'sqlite_'))
            ->reject(fn ($t) => in_array($t, $this->ausnehmen, true))
            ->values()->all();

        return $alle;
    }

    public function erstelle(string $anlass = 'manuell'): string
    {
        $daten = ['erstellt' => now()->toIso8601String(), 'anlass' => $anlass, 'version' => config('app.version'), 'tabellen' => []];
        foreach ($this->tabellen() as $tabelle) {
            $daten['tabellen'][$tabelle] = DB::table($tabelle)->get()->map(fn ($r) => (array) $r)->all();
        }

        $name = 'sicherung-'.now()->format('Y-m-d-His').'-'.$anlass.'.json.gz';
        Storage::disk('local')->put(self::ORDNER.'/'.$name, gzencode(json_encode($daten, JSON_UNESCAPED_UNICODE), 6));

        return $name;
    }

    public function liste(): array
    {
        return collect(Storage::disk('local')->files(self::ORDNER))
            ->filter(fn ($f) => str_ends_with($f, '.json.gz'))
            ->map(fn ($f) => [
                'name' => basename($f),
                'groesse' => Storage::disk('local')->size($f),
                'zeit' => Storage::disk('local')->lastModified($f),
            ])
            ->sortByDesc('zeit')->values()->all();
    }

    public function pfad(string $name): string
    {
        abort_unless(preg_match('/^sicherung-[\w\-]+\.json\.gz$/', $name), 404);

        return self::ORDNER.'/'.$name;
    }

    public function wiederherstellen(string $name): int
    {
        $inhalt = json_decode(gzdecode(Storage::disk('local')->get($this->pfad($name))), true);
        abort_unless(is_array($inhalt['tabellen'] ?? null), 422, 'Sicherung ist ungültig.');
        // Ein Datenexport enthaelt keine Passwoerter: eingespielt koennte sich niemand mehr anmelden
        $personen = $inhalt['tabellen']['users'] ?? [];
        abort_if($personen && ! array_key_exists('password', $personen[0]), 422, 'Das ist ein Datenexport, keine Sicherung. Er kann nicht eingespielt werden.');

        return $this->einspielen($inhalt['tabellen']);
    }

    public function einspielen(array $tabellen): int
    {
        $vorhanden = $this->tabellen();
        $zeilen = 0;

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($tabellen, $vorhanden, &$zeilen) {
                $ziel = array_values(array_filter(array_keys($tabellen), fn ($t) => in_array($t, $vorhanden, true)));
                // Alle Tabellen leeren, auch solche, die es zur Zeit der Sicherung noch nicht gab (sonst bleiben fremde Reste)
                foreach (array_reverse($this->nachAbhaengigkeit($vorhanden)) as $tabelle) {
                    DB::table($tabelle)->delete();
                }
                foreach ($this->nachAbhaengigkeit($ziel) as $tabelle) {
                    $spalten = Schema::getColumnListing($tabelle);
                    foreach (array_chunk($tabellen[$tabelle], 200) as $teil) {
                        $teil = array_map(fn ($r) => array_intersect_key($r, array_flip($spalten)), $teil);
                        DB::table($tabelle)->insert($teil);
                        $zeilen += count($teil);
                    }
                }
                $this->nachpflegen($tabellen);
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        \Illuminate\Support\Facades\Cache::flush();

        return $zeilen;
    }

    /** Aeltere Sicherung: Datenumstellungen nachholen, die seither per Migration dazugekommen sind. */
    protected function nachpflegen(array $tabellen): void
    {
        if (! array_key_exists('turnier_vorlage', $tabellen) && Schema::hasColumn('vorlagen', 'turnier_id')) {
            \App\Support\Datenpflege::turnierartenInPivot();
        }
        $aufgabe = $tabellen['aufgaben'][0] ?? null;
        if ($aufgabe && ! array_key_exists('tage_vorher', $aufgabe)) {
            \App\Support\Datenpflege::faelligkeitRelativ();
            \App\Support\Datenpflege::nachherAbEnde();
        } elseif (($tabellen['vorlagen'][0] ?? null) && ! array_key_exists('bezug', $tabellen['vorlagen'][0])) {
            \App\Support\Datenpflege::nachherAbEnde();
        }
    }

    /** Tabellen so ordnen, dass referenzierte Tabellen vor den abhaengigen kommen. */
    protected function nachAbhaengigkeit(array $tabellen): array
    {
        $eltern = [];
        foreach ($tabellen as $t) {
            $eltern[$t] = collect(Schema::getForeignKeys($t))->pluck('foreign_table')
                ->filter(fn ($f) => $f !== $t && in_array($f, $tabellen, true))->unique()->values()->all();
        }
        $sortiert = [];
        $besucht = [];
        $besuche = function (string $t) use (&$besuche, &$sortiert, &$besucht, $eltern) {
            if (isset($besucht[$t])) {
                return;
            }
            $besucht[$t] = true;
            foreach ($eltern[$t] as $e) {
                $besuche($e);
            }
            $sortiert[] = $t;
        };
        foreach ($tabellen as $t) {
            $besuche($t);
        }

        return $sortiert;
    }

    public function aufraeumen(int $tage): int
    {
        $grenze = now()->subDays($tage)->getTimestamp();
        $geloescht = 0;
        foreach ($this->liste() as $b) {
            // Sicherungen vor Leeren, Wiederherstellen, Updates und von Hand bleiben, bis jemand sie loescht
            if ($b['zeit'] < $grenze && str_ends_with($b['name'], '-automatisch.json.gz')) {
                Storage::disk('local')->delete(self::ORDNER.'/'.$b['name']);
                $geloescht++;
            }
        }

        return $geloescht;
    }
}
