<?php

namespace Database\Seeders;

use App\Models\Halle;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\Vorlage;
use Illuminate\Database\Seeder;

/**
 * Startstruktur fuer ein neues OK: Ressorts, die zwei Turniere und typische Vorlagen.
 * Alles ist danach im Tool frei anpassbar.
 */
class GrundSeeder extends Seeder
{
    public function run(): void
    {
        if (Ressort::exists()) {
            return;
        }

        $hA = Halle::create(['name' => 'Halle Dezember', 'ort' => '[Ort]', 'notizen' => 'Name, Adresse und Hallenwart unter Admin > Einstellungen erfassen.']);
        $hB = Halle::create(['name' => 'Halle Februar', 'ort' => '[Ort]', 'notizen' => 'Name, Adresse und Hallenwart unter Admin > Einstellungen erfassen.']);
        $dez = Turnier::create(['name' => 'Hallencup Dezember', 'kurz' => 'DEZ', 'halle_id' => $hA->id, 'beschreibung' => 'Erstes Turnierwochenende der Saison.']);
        $feb = Turnier::create(['name' => 'Hallencup Februar', 'kurz' => 'FEB', 'halle_id' => $hB->id, 'beschreibung' => 'Zweites Turnierwochenende der Saison.']);

        $ressorts = [
            ['Gesamtleitung', 'GL', 'Führt das OK, setzt Termine, hält die Fäden zusammen und vertritt das Turnier gegen aussen.'],
            ['Finanzen', 'FIN', 'Budget, Zahlungen, Kasse am Turnier und Schlussabrechnung.'],
            ['Sponsoring', 'SPO', 'Pflegt Hauptsponsor und Partner, verkauft Werbeflächen und bedankt sich nach dem Turnier.'],
            ['Spielbetrieb', 'SPB', 'Ausschreibung, Anmeldungen, Spielplan, Schiedsrichter, Reglement und Siegerehrung.'],
            ['Festwirtschaft', 'FW', 'Verpflegung von Teams, Gästen und Helfern. Einkauf, Preise, Kasse und Hygiene.'],
            ['Helfer', 'HLF', 'Bedarf planen, Helfer gewinnen, einteilen, betreuen und danken.'],
            ['Kommunikation', 'KOM', 'Plakat, Flyer, Social Media, Live-Resultate, Medien und Fotos im Styleguide.'],
            ['Infrastruktur', 'INF', 'Halle, Material, Aufbau, Abbau, Sanität und Sicherheit.'],
        ];
        $r = [];
        foreach ($ressorts as $i => [$name, $kurz, $auftrag]) {
            $r[$kurz] = Ressort::create(['name' => $name, 'kuerzel' => $kurz, 'auftrag' => $auftrag, 'sortierung' => $i]);
        }

        // [Ressort, Titel, Tage vorher, Aufwand, Turnier, Anleitung, Checkliste]
        $vorlagen = [
            ['GL', 'Turnierdaten festlegen und Halle reservieren', 300, 2, null, "Termine mit dem Regionalverband abgleichen (keine Kollision mit anderen Hallenturnieren).\nReservation bei der Gemeinde schriftlich bestätigen lassen.", ['Termin mit Verband prüfen', 'Halle reservieren', 'Bestätigung ablegen']],
            ['GL', 'OK-Startsitzung durchführen', 120, 3, null, "Traktanden: Rückblick letztes Turnier, offene Erkenntnisse, Budget, Verantwortlichkeiten. Protokoll im Ressort Gesamtleitung ablegen.", null],
            ['GL', 'Bewilligungen bei der Gemeinde einholen', 60, 1.5, null, "Gesuch für Festwirtschaft und Lautsprecher. Frist der Gemeinde beachten.", null],
            ['GL', 'Schlusssitzung und Rückblick', -14, 2, null, "Rückblick im Tool gemeinsam durchgehen. Jedes Ressort bringt seine Erkenntnisse mit.", null],
            ['FIN', 'Budget erstellen', 120, 3, null, "Vorlage: Abrechnung des letzten Turniers. Preise Festwirtschaft und Startgeld prüfen.", null],
            ['FIN', 'Startgelder einfordern', 30, 1, null, "Rechnungen an alle angemeldeten Teams, Zahlungsfrist 14 Tage vor dem Turnier.", null],
            ['FIN', 'Kasse und Wechselgeld vorbereiten', 3, 1, null, "Wechselgeld bei der Bank bestellen. Kassenblätter ausdrucken.", ['Wechselgeld bestellen', 'Kassenblätter drucken', 'Kartenterminal laden']],
            ['FIN', 'Schlussabrechnung erstellen', -21, 3, null, "Alle Belege sammeln, Abrechnung an Gesamtleitung und Vereinskasse.", null],
            ['SPO', 'Sponsorenanfragen versenden', 150, 4, null, "Bestehende Partner zuerst persönlich anfragen. Vorlage Sponsorenanfrage im FCM-Styleguide verwenden.", null],
            ['SPO', 'Werbebanner in der Halle koordinieren', 21, 2, null, "Bannerliste mit Platzierung an Infrastruktur geben. Hauptsponsor immer an bester Stelle.", null],
            ['SPO', 'Dank an Sponsoren', -10, 2, null, "Kurzer Bericht mit Fotos und Resultaten. Persönlich unterschrieben.", null],
            ['SPB', 'Turnierreglement aktualisieren', 130, 2, null, "Änderungen des Verbandes einarbeiten. Rückmeldungen der Schiedsrichter berücksichtigen.", null],
            ['SPB', 'Ausschreibung an Teams versenden', 120, 2, null, "Ausschreibung an Vereine der Region. Anmeldeschluss klar angeben.", null],
            ['SPB', 'Anmeldungen sammeln und bestätigen', 45, 3, null, "Anmeldeliste nachführen, Bestätigung an jedes Team.", null],
            ['SPB', 'Schiedsrichter aufbieten', 30, 1.5, null, "Bei der Schiedsrichtervereinigung anfragen. Einsatzzeiten mitteilen.", null],
            ['SPB', 'Pokale und Medaillen bestellen', 35, 1, null, "Lieferzeit beachten (mind. 3 Wochen). Gravur prüfen.", null],
            ['SPB', 'Spielplan erstellen', 14, 4, null, "Gruppen auslosen, Spielplan im Styleguide als PDF.", null],
            ['SPB', 'Rundschreiben an Teams', 10, 1.5, null, "Spielplan, Anfahrt, Garderoben, Reglement, Kontakt am Turniertag.", null],
            ['FW', 'Hygienekonzept prüfen', 30, 1, null, null, null],
            ['FW', 'Getränke und Lebensmittel bestellen', 14, 2, null, "Mengen des letzten Turniers als Basis nehmen (siehe Erkenntnisse).", null],
            ['FW', 'Preisliste und Kassensystem', 7, 1, null, null, null],
            ['FW', 'Einkauf Frischwaren', 2, 3, null, null, null],
            ['HLF', 'Helferbedarf festlegen', 60, 1, null, "Schichten pro Ressort abfragen.", null],
            ['HLF', 'Helferaufruf an Teams und Eltern', 45, 1, null, null, null],
            ['HLF', 'Helfereinteilung versenden', 10, 3, null, "Einteilung im Styleguide, Treffpunkt und Kontakt angeben.", null],
            ['HLF', 'Helferessen organisieren', 14, 1, null, null, null],
            ['KOM', 'Plakat und Flyer erstellen', 60, 3, null, "Immer im Raiffeisen-Hallencup Styleguide. Formatcheck vor dem Druck.", null],
            ['KOM', 'Ankündigung auf Social Media', 30, 1, null, null, null],
            ['KOM', 'Live-Resultate vorbereiten', 3, 2, null, null, null],
            ['KOM', 'Medienmitteilung nach dem Turnier', -2, 1.5, null, null, null],
            ['KOM', 'Fotos sammeln und ablegen', -7, 1, null, "Ordner 04_Fotos_Videos, nach Saison sortiert.", null],
            ['INF', 'Material und Inventar prüfen', 30, 2, null, null, null],
            ['INF', 'Sanität aufbieten', 30, 0.5, null, null, null],
            ['INF', 'Schlüsselübergabe mit Hallenwart', 7, 0.5, null, null, null],
            ['INF', 'Aufbau Halle', 1, 6, null, null, ['Banden stellen', 'Tore prüfen', 'Festwirtschaft aufbauen', 'Lautsprecher testen']],
            ['INF', 'Abbau und Reinigung', 0, 5, null, null, null],
            ['HLF', 'Weihnachtsüberraschung für Juniorenteams', 21, 1, 'DEZ', null, null],
        ];

        foreach ($vorlagen as [$res, $titel, $tage, $std, $turnier, $anleitung, $liste]) {
            $vorlage = Vorlage::create([
                'ressort_id' => $r[$res]->id,
                'titel' => $titel,
                'tage_vorher' => $tage,
                'aufwand_std' => $std,
                'anleitung' => $anleitung,
                'checkliste' => $liste,
                'prioritaet' => in_array($titel, ['Turnierdaten festlegen und Halle reservieren', 'Schiedsrichter aufbieten', 'Bewilligungen bei der Gemeinde einholen'], true) ? 'hoch' : 'normal',
            ]);
            if ($turnier) {
                $vorlage->setzeTurniere([$turnier === 'DEZ' ? $dez->id : $feb->id]);
            }
        }
    }
}
