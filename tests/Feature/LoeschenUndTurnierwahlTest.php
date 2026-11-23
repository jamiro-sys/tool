<?php

namespace Tests\Feature;

use App\Models\Aufgabe;
use App\Models\Ausgabe;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\Vorlage;

/** Aufgaben und Jahresplan-Eintraege loeschen, klare Begriffe, Turnierwahl in der Kopfzeile. */
class LoeschenUndTurnierwahlTest extends OkTestCase
{
    protected function turnier(string $kurz, string $saison, string $datum): Ausgabe
    {
        return Ausgabe::create(['turnier_id' => Turnier::where('kurz', $kurz)->value('id'), 'saison' => $saison, 'titel' => $kurz.' '.$saison, 'datum_von' => $datum, 'status' => 'planung']);
    }

    public function test_aufgabe_loeschen_mit_und_ohne_jahresplan(): void
    {
        $r = Ressort::first();
        $leitung = $this->person('mitglied', $r->kuerzel, 'leitung');
        $mitglied = $this->person('mitglied', $r->kuerzel);
        $a = $this->turnier('DEZ', '2030/31', '2030-12-07');
        $v = Vorlage::create(['ressort_id' => $r->id, 'titel' => 'Halle QQ', 'tage_vorher' => 30, 'aktiv' => true]);
        $eins = Aufgabe::create(['ausgabe_id' => $a->id, 'vorlage_id' => $v->id, 'ressort_id' => $r->id, 'titel' => 'Halle QQ', 'status' => 'offen']);
        $zwei = Aufgabe::create(['ausgabe_id' => $a->id, 'vorlage_id' => $v->id, 'ressort_id' => $r->id, 'titel' => 'Halle QQ doppelt', 'status' => 'offen']);

        // Loeschen ist fuer die Leitung sichtbar, fuer Mitglieder nicht
        $this->actingAs($leitung)->get(route('aufgaben.show', $eins))->assertOk()->assertSee('data-loeschen', false)
            ->assertSee('Auch im Jahresplan auf «Kommt nicht mehr» stellen');
        $this->actingAs($mitglied)->get(route('aufgaben.show', $eins))->assertOk()->assertDontSee('data-loeschen', false);
        $this->actingAs($mitglied)->delete(route('aufgaben.destroy', $eins))->assertForbidden();

        // Ohne Haekchen: Jahresplan bleibt
        $this->actingAs($leitung)->delete(route('aufgaben.destroy', $zwei))->assertRedirect(route('aufgaben.index', $a));
        $this->assertNull(Aufgabe::find($zwei->id));
        $this->assertTrue($v->fresh()->aktiv);

        // Mit Haekchen: kommt nicht mehr
        $this->actingAs($leitung)->delete(route('aufgaben.destroy', $eins), ['aus_jahresplan' => 1]);
        $this->assertNull(Aufgabe::find($eins->id));
        $this->assertFalse($v->fresh()->aktiv);
    }

    public function test_mitglied_loescht_eigene_aufgabe(): void
    {
        $r = Ressort::first();
        $ich = $this->person('mitglied', $r->kuerzel);
        $andere = $this->person('mitglied', $r->kuerzel);
        $a = $this->turnier('DEZ', '2030/31', '2030-12-07');
        $v = Vorlage::create(['ressort_id' => $r->id, 'titel' => 'Eigen QQ', 'tage_vorher' => 30, 'aktiv' => true]);
        $meine = Aufgabe::create(['ausgabe_id' => $a->id, 'vorlage_id' => $v->id, 'ressort_id' => $r->id, 'titel' => 'Eigen QQ', 'status' => 'offen', 'verantwortlich_id' => $ich->id]);
        $fremde = Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'titel' => 'Fremd QQ', 'status' => 'offen', 'verantwortlich_id' => $andere->id]);

        // Eigene Aufgabe: Knopf sichtbar, aber ohne Jahresplan-Haekchen
        $this->actingAs($ich)->get(route('aufgaben.show', $meine))->assertOk()->assertSee('data-loeschen', false)
            ->assertDontSee('Auch im Jahresplan auf «Kommt nicht mehr» stellen')->assertSee('sag es der Ressortleitung');
        $this->actingAs($ich)->get(route('aufgaben.show', $fremde))->assertOk()->assertDontSee('data-loeschen', false);
        $this->actingAs($ich)->delete(route('aufgaben.destroy', $fremde))->assertForbidden();

        // Lesende Person darf auch eigene nicht loeschen
        $lesend = $this->person('lesend');
        $fremde->update(['verantwortlich_id' => $lesend->id]);
        $this->actingAs($lesend)->delete(route('aufgaben.destroy', $fremde))->assertForbidden();

        // Jahresplan bleibt, auch wenn das Haekchen mitgeschickt wird
        $this->actingAs($ich)->delete(route('aufgaben.destroy', $meine), ['aus_jahresplan' => 1])->assertRedirect(route('aufgaben.index', $a));
        $this->assertNull(Aufgabe::find($meine->id));
        $this->assertTrue($v->fresh()->aktiv);
    }

    public function test_jahresplan_eintrag_loeschen(): void
    {
        $r = Ressort::first();
        $leitung = $this->person('mitglied', $r->kuerzel, 'leitung');
        $a = $this->turnier('DEZ', '2030/31', '2030-12-07');
        $v = Vorlage::create(['ressort_id' => $r->id, 'titel' => 'Weg QQ', 'tage_vorher' => 10, 'aktiv' => true]);
        $aufgabe = Aufgabe::create(['ausgabe_id' => $a->id, 'vorlage_id' => $v->id, 'ressort_id' => $r->id, 'titel' => 'Weg QQ', 'status' => 'offen']);

        $this->actingAs($this->person('mitglied', $r->kuerzel))->delete(route('vorlagen.destroy', $v))->assertForbidden();
        $this->actingAs($leitung)->get(route('vorlagen.show', $v))->assertSee('Aus dem Jahresplan löschen');
        $this->actingAs($leitung)->delete(route('vorlagen.destroy', $v))->assertRedirect(route('vorlagen.index'));
        $this->assertNull(Vorlage::find($v->id));
        // Aufgabe im Turnier bleibt, gilt jetzt als "Nur dieses Mal"
        $this->assertNull($aufgabe->fresh()->vorlage_id);
        $this->actingAs($leitung)->get(route('aufgaben.show', $aufgabe))->assertSee('Nur dieses Mal');
    }

    public function test_wie_oft_drei_klare_wahlen(): void
    {
        $r = Ressort::first();
        $leitung = $this->person('mitglied', $r->kuerzel, 'leitung');
        $a = $this->turnier('DEZ', '2030/31', '2030-12-07');
        $seite = $this->actingAs($leitung)->get(route('aufgaben.index', $a));
        $seite->assertSeeInOrder(['Nur dieses Mal', 'Bei jedem Turnier', 'Einmal pro Saison']);
        $this->actingAs($leitung)->get(route('vorlagen.index'))->assertSee('So hängt alles zusammen')->assertSee('Kommt wieder?');

        // Formular sendet weiterhin wiederkehrend + haeufigkeit
        $this->actingAs($leitung)->post(route('aufgaben.store', $a), ['titel' => 'Einmal QQ', 'ressort_id' => $r->id, 'wiederkehrend' => 'einmalig', 'haeufigkeit' => 'turnier'])->assertSessionHasNoErrors();
        $this->assertNull(Aufgabe::where('titel', 'Einmal QQ')->value('vorlage_id'));
    }

    public function test_kopfzeile_turnierwahl(): void
    {
        $admin = $this->person('admin');
        $dez = $this->turnier('DEZ', '2030/31', today()->addDays(20)->toDateString());
        $feb = $this->turnier('FEB', '2030/31', today()->addDays(90)->toDateString());

        // Kein festes Datum mehr im Titel, dafuer die Turnierwahl mit dem naechsten Turnier
        $start = $this->actingAs($admin)->get(route('start'))->assertOk()->assertSee('data-turnierwahl', false)->assertSee('OK-TOOL');
        $start->assertSee('DEZ '.$dez->datum_von->format('Y'));

        // Wechsel auf der Aufgabenliste: gleiche Ansicht fuer das andere Turnier
        $this->actingAs($admin)->get(route('ausgaben.waehlen', ['ausgabe' => $feb, 'ansicht' => 'aufgaben.index']))->assertRedirect(route('aufgaben.index', $feb));
        $this->assertSame($feb->id, session(Ausgabe::WAHL));
        // Ohne turnierbezogene Ansicht zeigt danach auch die Startseite das gewaehlte Turnier
        $this->actingAs($admin)->get(route('start'))->assertSee('FEB '.$feb->datum_von->format('Y'));

        // Turnierseite oeffnen setzt die Wahl ebenfalls
        $this->actingAs($admin)->get(route('ausgaben.show', $dez))->assertOk();
        $this->assertSame($dez->id, session(Ausgabe::WAHL));

        // Fremde Ruecksprung-Adresse wird nicht verwendet
        $this->actingAs($admin)->withHeader('referer', 'https://example.org/x')->get(route('ausgaben.waehlen', $feb))->assertRedirect(route('start'));
    }
}
