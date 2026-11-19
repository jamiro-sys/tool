<?php

namespace Database\Seeders;

use App\Models\Ausgabe;
use App\Models\Aufgabe;
use App\Models\Erkenntnis;
use App\Models\Kontakt;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\User;
use App\Models\Vorlage;
use App\Models\Wissen;
use App\Services\AusgabePlaner;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/** Demodaten zum Ausprobieren (nur lokal). Alle Personen haben das Passwort "passwort". */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $personen = [
            ['Lena Meier', 'admin', 'experte', 'GL', 'leitung'],
            ['Noah Keller', 'mitglied', 'experte', 'FIN', 'leitung'],
            ['Mia Brunner', 'mitglied', 'einfach', 'SPO', 'leitung'],
            ['Luca Frei', 'mitglied', 'experte', 'SPB', 'leitung'],
            ['Sara Huber', 'mitglied', 'einfach', 'FW', 'leitung'],
            ['Jonas Weber', 'mitglied', 'einfach', 'HLF', 'leitung'],
            ['Elena Graf', 'mitglied', 'einfach', 'KOM', 'leitung'],
            ['Tim Baumann', 'mitglied', 'einfach', 'INF', 'leitung'],
            ['Nina Schmid', 'mitglied', 'einfach', 'KOM', 'nachfolge'],
            ['Ben Roth', 'lesend', 'einfach', 'SPB', 'nachfolge'],
        ];
        $ressorts = Ressort::all()->keyBy('kuerzel');
        $u = [];
        foreach ($personen as [$name, $rolle, $ansicht, $res, $rr]) {
            $email = strtolower(str_replace(' ', '.', $name)).'@example.ch';
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name, 'password' => 'passwort', 'rolle' => $rolle, 'ansicht' => $ansicht,
                'telefon' => '079 000 00 '.str_pad((string) count($u), 2, '0', STR_PAD_LEFT), 'email_verified_at' => now(),
            ]);
            $user->ressorts()->syncWithoutDetaching([$ressorts[$res]->id => ['rolle' => $rr, 'seit' => now()->subYears(2)]]);
            $u[$res.$rr] = $user;
        }
        $u['SPBleitung']->ressorts()->syncWithoutDetaching([$ressorts['GL']->id => ['rolle' => 'mitglied']]);
        $u['GLleitung']->ressorts()->syncWithoutDetaching([$ressorts['SPO']->id => ['rolle' => 'mitglied']]);

        $planer = app(AusgabePlaner::class);
        $dez = Turnier::where('kurz', 'DEZ')->first();
        $feb = Turnier::where('kurz', 'FEB')->first();

        auth()->login($u['GLleitung']);

        $ausgaben = [
            [$dez, '2025-12-06', '2025-12-07', '2025/26', 'abgeschlossen'],
            [$feb, '2026-02-14', '2026-02-15', '2025/26', 'abgeschlossen'],
            [$dez, '2026-12-05', '2026-12-06', '2026/27', 'planung'],
        ];
        foreach ($ausgaben as [$turnier, $von, $bis, $saison, $status]) {
            $datum = Carbon::parse($von);
            $auswahl = $planer->vorschlag($turnier, $datum)->mapWithKeys(fn ($e) => [$e['vorlage']->id => ['aktion' => 'uebernehmen', 'verantwortlich_id' => $e['verantwortlich_id']]])->all();
            $a = $planer->erstelle($turnier, ['datum_von' => $von, 'datum_bis' => $bis, 'saison' => $saison, 'titel' => $turnier->name.' '.$saison], $auswahl);
            $a->update(['status' => $status]);

            foreach ($a->aufgaben as $i => $aufgabe) {
                $vergangen = $aufgabe->faellig_am->lt(today());
                if ($status === 'abgeschlossen' || ($vergangen && ($i % 5 !== 0 || $aufgabe->faellig_am->lt(today()->subDays(30))))) {
                    $verspaetung = $i % 4 === 0 ? 3 : -1;
                    $aufgabe->update([
                        'status' => 'erledigt',
                        'erledigt_am' => $aufgabe->faellig_am->copy()->addDays($verspaetung)->setTime(19, 30),
                        'erledigt_von' => $aufgabe->verantwortlich_id,
                        'aufwand_effektiv' => $aufgabe->aufwand_geplant ? round($aufgabe->aufwand_geplant * (0.7 + ($i % 6) / 10), 1) : null,
                    ]);
                } elseif ($vergangen) {
                    $aufgabe->update(['status' => 'in_arbeit']);
                }
            }
        }

        $aktuell = Ausgabe::where('status', 'planung')->first();
        $erste = Ausgabe::orderBy('datum_von')->first();

        // Eine Aufgabe ohne Zuteilung und eine mit Begleitung
        $aktuell->aufgaben()->whereHas('vorlage', fn ($q) => $q->where('titel', 'Live-Resultate vorbereiten'))->update(['verantwortlich_id' => null]);
        $aktuell->aufgaben()->whereHas('vorlage', fn ($q) => $q->where('titel', 'Plakat und Flyer erstellen'))->update(['begleitung_id' => $u['KOMnachfolge']->id]);
        Aufgabe::create(['ausgabe_id' => $aktuell->id, 'ressort_id' => $ressorts['KOM']->id, 'titel' => 'Programmheft mit Sponsorenseite', 'faellig_am' => '2026-11-28', 'verantwortlich_id' => $u['KOMleitung']->id, 'beschreibung' => 'Neu in dieser Ausgabe.']);

        // Erkenntnisse aus dem letzten Dezember
        $getraenke = Vorlage::where('titel', 'Getränke und Lebensmittel bestellen')->first();
        $pokale = Vorlage::where('titel', 'Pokale und Medaillen bestellen')->first();
        $hygiene = Vorlage::where('titel', 'Hygienekonzept prüfen')->first();
        Erkenntnis::create(['ressort_id' => $ressorts['FW']->id, 'ausgabe_id' => $erste->id, 'vorlage_id' => $getraenke->id, 'user_id' => $u['FWleitung']->id, 'text' => 'Am Sonntag war das Mineralwasser um 14 Uhr aus. 20 % mehr bestellen.', 'vorschlag' => 'anpassen']);
        Erkenntnis::create(['ressort_id' => $ressorts['SPB']->id, 'ausgabe_id' => $erste->id, 'vorlage_id' => $pokale->id, 'user_id' => $u['SPBleitung']->id, 'text' => 'Lieferung kam erst zwei Tage vor dem Turnier. Eine Woche früher bestellen.', 'vorschlag' => 'anpassen']);
        Erkenntnis::create(['ressort_id' => $ressorts['FW']->id, 'ausgabe_id' => $erste->id, 'vorlage_id' => $hygiene->id, 'user_id' => $u['FWleitung']->id, 'text' => 'Die Gemeinde prüft das Konzept neu selbst, Aufgabe ist überflüssig.', 'vorschlag' => 'streichen', 'umgesetzt_am' => now()->subMonths(8), 'umgesetzt_von' => $u['FWleitung']->id]);
        $hygiene->update(['aktiv' => false, 'deaktiviert_am' => now()->subMonths(8), 'deaktiviert_grund' => 'Gemeinde prüft selbst']);

        Kontakt::create(['ressort_id' => $ressorts['INF']->id, 'name' => '[Hallenwart]', 'funktion' => 'Hallenwart', 'telefon' => '[Telefon]', 'notizen' => 'Schlüssel jeweils am Freitag vor dem Turnier abholen.']);
        Kontakt::create(['ressort_id' => $ressorts['SPO']->id, 'name' => '[Ansprechperson Raiffeisenbank]', 'firma' => 'Raiffeisenbank Regio Altnau', 'funktion' => 'Hauptsponsor']);
        Wissen::create(['ressort_id' => $ressorts['FW']->id, 'user_id' => $u['FWleitung']->id, 'titel' => 'Ablauf am Turniertag', 'inhalt' => "07.00 Uhr Aufbau Theke\n08.30 Uhr Kaffee bereit\n12.00 Uhr Mittagsservice, 4 Personen an der Theke\n17.00 Uhr Kassensturz mit Finanzen"]);
        Wissen::create(['ressort_id' => $ressorts['SPB']->id, 'user_id' => $u['SPBleitung']->id, 'titel' => 'Spielplan: so geht die Auslosung', 'inhalt' => "Gruppen mit je 5 Teams. Gesetzte Teams: Vorjahressieger und Gastgeber.\nDie Vorlage liegt in den Dokumenten."]);

        auth()->logout();
    }
}
