<?php

namespace Tests\Feature;

use App\Models\Aufgabe;
use App\Models\Ausgabe;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\Vorlage;
use App\Support\Aufwand;
use App\Support\Dateiname;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class WiederkehrendTest extends OkTestCase
{
    private function turnier(): Ausgabe
    {
        return Ausgabe::create(['turnier_id' => Turnier::first()->id, 'saison' => '2026/27', 'titel' => 'Hallencup Test', 'datum_von' => today()->addDays(40), 'status' => 'planung']);
    }

    public function test_aufwand_text_in_stunden_und_minuten(): void
    {
        $this->assertSame('', Aufwand::text(null));
        $this->assertSame('0 Min.', Aufwand::text(0));
        $this->assertSame('15 Min.', Aufwand::text(0.25));
        $this->assertSame('2 Std.', Aufwand::text(2));
        $this->assertSame('1 Std. 30 Min.', Aufwand::text(1.5));
    }

    public function test_aufwand_mit_minuten_wird_gespeichert(): void
    {
        $a = $this->turnier();
        $r = Ressort::first();
        $aufgabe = Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'titel' => 'Hallenwart anrufen', 'status' => 'offen', 'aufwand_geplant' => 0.25]);
        $this->assertSame(0.25, (float) $aufgabe->fresh()->aufwand_geplant);
        $this->actingAs($this->person('admin'))->get(route('aufgaben.show', $aufgabe))->assertOk()->assertSee('15 Min.');
    }

    public function test_einmalige_aufgabe_jedes_turnier_wiederholen(): void
    {
        $a = $this->turnier();
        $r = Ressort::first();
        $aufgabe = Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'titel' => 'Beamer reservieren XYZ', 'status' => 'offen', 'faellig_am' => $a->datum_von->copy()->subDays(10), 'aufwand_geplant' => 0.5]);

        $this->actingAs($this->person('mitglied', $r->kuerzel))->post(route('aufgaben.wiederholen', $aufgabe))->assertForbidden();

        $leitung = $this->person('mitglied', $r->kuerzel, 'leitung');
        $this->actingAs($leitung)->get(route('aufgaben.show', $aufgabe))->assertSee('Wie oft?')->assertDontSee('Im Jahresplan ansehen');
        $this->actingAs($leitung)->post(route('aufgaben.wiederholen', $aufgabe))->assertRedirect();

        $vorlage = Vorlage::where('titel', 'Beamer reservieren XYZ')->firstOrFail();
        $this->assertTrue($vorlage->aktiv);
        $this->assertSame(10, $vorlage->tage_vorher);
        $this->assertSame(0.5, $vorlage->aufwand_std);
        $this->assertSame($vorlage->id, $aufgabe->fresh()->vorlage_id);

        $this->actingAs($leitung)->get(route('aufgaben.show', $aufgabe))->assertSee('Im Jahresplan ansehen');
        $this->actingAs($leitung)->post(route('aufgaben.wiederholen', $aufgabe))->assertStatus(409);
    }

    public function test_vorlage_wiederkehrend_ja_nein(): void
    {
        $admin = $this->person('admin');
        $vorlage = Vorlage::create(['ressort_id' => Ressort::first()->id, 'titel' => 'Halle buchen XYZ', 'tage_vorher' => 90]);
        $this->actingAs($admin)->get(route('vorlagen.show', $vorlage))->assertOk()->assertSee('Jedes Jahr');
        $this->actingAs($admin)->post(route('vorlagen.aktiv', $vorlage), ['aktiv' => 0, 'grund' => 'Halle fix gebucht'])->assertRedirect();
        $this->assertFalse($vorlage->fresh()->aktiv);
        $this->actingAs($admin)->get(route('vorlagen.show', $vorlage))->assertSee('nicht mehr bei neuen Turnieren');
        $this->actingAs($admin)->post(route('vorlagen.aktiv', $vorlage), ['aktiv' => 1])->assertRedirect();
        $this->assertTrue($vorlage->fresh()->aktiv);
    }

    public function test_titel_aus_dateiname(): void
    {
        $this->assertSame('Spielplan hallencup 2026', Dateiname::titel('spielplan_hallencup_2026.pdf'));
        $this->assertSame('Raiffeisen-Hallencup Plakat', Dateiname::titel('Raiffeisen-Hallencup Plakat (1).PNG'));
        $this->assertSame('Budget', Dateiname::titel('budget Kopie.xlsx'));

        Storage::fake('local');
        $this->actingAs($this->person('admin'))->post(route('dateien.speichern'), [
            'dateien' => [UploadedFile::fake()->create('helfer_liste_dez.pdf', 10), UploadedFile::fake()->create('preise-festzelt.pdf', 10)],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('dokumente', ['titel' => 'Helfer liste dez']);
        $this->assertDatabaseHas('dokumente', ['titel' => 'Preise-festzelt']);
    }

    public function test_jahresplan_zeigt_alle_ressorts_und_schnellerfassung(): void
    {
        $admin = $this->person('admin');
        $r = Ressort::orderBy('sortierung')->first();
        $leer = Ressort::create(['name' => 'Leeres Ressort XYZ', 'kuerzel' => 'LRX', 'sortierung' => 99]);
        $this->actingAs($admin)->get(route('vorlagen.index'))->assertOk()
            ->assertSee('Jahresplan')->assertSee('Neu in den Jahresplan')->assertSee('Leeres Ressort XYZ')->assertSee('Noch nichts im Jahresplan');

        $this->actingAs($admin)->post(route('vorlagen.store'), ['schnell' => 1, 'titel' => 'Halle reservieren QQQ', 'ressort_id' => $r->id, 'tage_vorher' => 300, 'prioritaet' => 'normal', 'aufwand_std' => 0.75])
            ->assertRedirect(route('vorlagen.index'))->assertSessionHas('schnell');
        $v = Vorlage::where('titel', 'Halle reservieren QQQ')->firstOrFail();
        $this->assertTrue($v->aktiv);
        $this->assertSame('10 Monate vorher', $v->zeitpunktText());
        $this->actingAs($admin)->get(route('vorlagen.index'))->assertSee('Halle reservieren QQQ')->assertSee('10 Monate vorher');

        // Schalter in der Liste per fetch
        $this->actingAs($admin)->postJson(route('vorlagen.aktiv', $v), ['aktiv' => 0])->assertOk()->assertJson(['wert' => false]);
        $this->assertFalse($v->fresh()->aktiv);
        $this->actingAs($admin)->get(route('vorlagen.index', ['status' => 'ja']))->assertDontSee('Halle reservieren QQQ');
        $this->actingAs($admin)->get(route('vorlagen.index', ['status' => 'nein']))->assertSee('Halle reservieren QQQ');

        // Mitglied ohne Leitung sieht keine Schnellerfassung und darf nicht schalten
        $m = $this->person('mitglied', $r->kuerzel);
        $this->actingAs($m)->get(route('vorlagen.index'))->assertOk()->assertDontSee('Neu in den Jahresplan');
        $this->actingAs($m)->postJson(route('vorlagen.aktiv', $v), ['aktiv' => 1])->assertForbidden();
    }

    public function test_neue_aufgabe_jedes_jahr_oder_einmalig(): void
    {
        $a = $this->turnier();
        $r = Ressort::first();
        $leitung = $this->person('mitglied', $r->kuerzel, 'leitung');
        $this->actingAs($leitung)->get(route('aufgaben.index', $a))->assertSee('Wie oft?')->assertSee('Nur dieses Turnier');

        $this->actingAs($leitung)->post(route('aufgaben.store', $a), ['titel' => 'Musikboxen ausleihen QQQ', 'ressort_id' => $r->id, 'wiederkehrend' => 'einmalig', 'faellig_am' => $a->datum_von->copy()->subDays(7)->format('Y-m-d')])->assertRedirect();
        $this->assertNull(Aufgabe::where('titel', 'Musikboxen ausleihen QQQ')->value('vorlage_id'));

        $this->actingAs($leitung)->post(route('aufgaben.store', $a), ['titel' => 'Schiris aufbieten QQQ', 'ressort_id' => $r->id, 'wiederkehrend' => 'jedes_jahr', 'faellig_am' => $a->datum_von->copy()->subDays(28)->format('Y-m-d')])->assertRedirect();
        $aufgabe = Aufgabe::where('titel', 'Schiris aufbieten QQQ')->firstOrFail();
        $this->assertNotNull($aufgabe->vorlage_id);
        $this->assertSame(28, $aufgabe->vorlage->tage_vorher);
        $this->assertSame('4 Wochen vorher', $aufgabe->vorlage->zeitpunktText());

        // Mitglied ohne Leitung: Auswahl nicht sichtbar, jedes_jahr wird ignoriert
        $m = $this->person('mitglied', $r->kuerzel);
        $this->actingAs($m)->get(route('aufgaben.index', $a))->assertDontSee('Wie oft?');
        $this->actingAs($m)->post(route('aufgaben.store', $a), ['titel' => 'Kaffee QQQ', 'ressort_id' => $r->id, 'wiederkehrend' => 'jedes_jahr'])->assertRedirect();
        $this->assertNull(Aufgabe::where('titel', 'Kaffee QQQ')->value('vorlage_id'));
    }

    public function test_rueckblick_naechstes_jahr_ja_nein(): void
    {
        $a = $this->turnier();
        $r = Ressort::first();
        $admin = $this->person('admin');
        $einmalig = Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'titel' => 'Beamer leihen QQQ', 'status' => 'erledigt', 'faellig_am' => $a->datum_von->copy()->subDays(14)]);
        $vorlage = Vorlage::create(['ressort_id' => $r->id, 'titel' => 'Musikboxen QQQ', 'tage_vorher' => 7, 'aktiv' => true]);
        $wiederkehrend = Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'titel' => 'Musikboxen QQQ', 'status' => 'erledigt', 'vorlage_id' => $vorlage->id]);

        $this->actingAs($admin)->get(route('ausgaben.rueckblick', $a))->assertOk()->assertSee('Nächstes Jahr');

        // Nein: kommt nicht mehr
        $this->actingAs($admin)->postJson(route('aufgaben.naechstes-jahr', $wiederkehrend), ['wert' => 0])->assertJson(['wert' => false]);
        $this->assertFalse($vorlage->fresh()->aktiv);
        $this->assertStringContainsString('Rückblick', $vorlage->fresh()->deaktiviert_grund);

        // Ja bei einmaliger Aufgabe: in den Jahresplan
        $this->actingAs($admin)->postJson(route('aufgaben.naechstes-jahr', $einmalig), ['wert' => 1])->assertJson(['wert' => true]);
        $this->assertSame(14, $einmalig->fresh()->vorlage->tage_vorher);

        // Ja bei herausgenommener Aufgabe: wieder im Jahresplan, keine doppelte Vorlage
        $this->actingAs($admin)->postJson(route('aufgaben.naechstes-jahr', $wiederkehrend), ['wert' => 1])->assertJson(['wert' => true]);
        $this->assertTrue($vorlage->fresh()->aktiv);
        $this->assertSame(1, Vorlage::where('titel', 'Musikboxen QQQ')->count());

        $this->actingAs($this->person('mitglied', $r->kuerzel))->postJson(route('aufgaben.naechstes-jahr', $einmalig), ['wert' => 0])->assertForbidden();
    }

    public function test_zeitpunkt_feld_rechnet_in_tagen(): void
    {
        $html = $this->blade('<x-zeitpunkt :wert="60" />');
        $html->assertSee('value="2"', false)->assertSee('value="60"', false)->assertSee('Monate');
        $this->blade('<x-zeitpunkt :wert="21" />')->assertSee('value="3"', false);
        $this->blade('<x-zeitpunkt :wert="10" />')->assertSee('value="10"', false);
    }

    public function test_jahresplan_fuer_alle_lesbar(): void
    {
        [$r1, $r2] = Ressort::orderBy('sortierung')->take(2)->get()->all();
        Vorlage::create(['ressort_id' => $r1->id, 'titel' => 'Eigene Aufgabe QQQ', 'tage_vorher' => 30]);
        Vorlage::create(['ressort_id' => $r2->id, 'titel' => 'Fremde Aufgabe QQQ', 'tage_vorher' => 60]);
        $leer = Ressort::create(['name' => 'Leeres Ressort ZZZ', 'kuerzel' => 'LZZ', 'sortierung' => 99]);

        $m = $this->person('mitglied', $r1->kuerzel);
        $this->actingAs($m)->get(route('vorlagen.index'))->assertOk()
            ->assertSee('Eigene Aufgabe QQQ')->assertSee('Fremde Aufgabe QQQ')->assertSee('Änderungen macht die Ressortleitung')
            ->assertDontSee('Neu in den Jahresplan')->assertDontSee('aria-pressed', false)->assertDontSee('Noch nichts im Jahresplan.');
        $this->actingAs($m)->get(route('vorlagen.index', ['ressort' => 'meine']))->assertOk()
            ->assertSee('Eigene Aufgabe QQQ')->assertDontSee('Fremde Aufgabe QQQ');

        $leitung = $this->person('mitglied', $r1->kuerzel, 'leitung');
        $seite = $this->actingAs($leitung)->get(route('vorlagen.index'))->assertSee('Neu in den Jahresplan')->assertSee('Noch nichts im Jahresplan.');
        $this->assertStringNotContainsString('<option value="'.$r2->id.'"', explode('id="s-ressort"', $seite->getContent())[1] ? explode('</select>', explode('id="s-ressort"', $seite->getContent())[1])[0] : '');

        $this->actingAs($this->person('lesend'))->get(route('vorlagen.index'))->assertOk()->assertSee('Eigene Aufgabe QQQ');
    }

    public function test_aufgabe_aus_ressort_erfassen_und_turnierarten_waehlen(): void
    {
        $a = $this->turnier();
        $r = Ressort::first();
        $dez = Turnier::where('kurz', 'DEZ')->first();
        $feb = Turnier::where('kurz', 'FEB')->first();
        $leitung = $this->person('mitglied', $r->kuerzel, 'leitung');

        // Knopf im Ressort, Formular mit vorgewaehltem Ressort und Turnierwahl (aktuelles Turnier angehakt)
        $seite = $this->actingAs($leitung)->get(route('ressorts.show', [$r, 'tab' => 'aufgaben']))->assertOk();
        $seite->assertSee("modal-neue-aufgabe", false)->assertSee('Bei welchen Turnieren?')->assertSee(route('aufgaben.store', $a), false);
        $this->assertMatchesRegularExpression('/value="'.$a->turnier_id.'" checked/', $seite->getContent());

        $zurueck = route('ressorts.show', [$r, 'tab' => 'aufgaben'], false);
        $this->actingAs($leitung)->post(route('aufgaben.store', $a), ['titel' => 'Beide QQQ', 'ressort_id' => $r->id, 'wiederkehrend' => 'jedes_jahr', 'turniere_gesendet' => 1, 'turniere' => [$dez->id, $feb->id], 'zurueck' => $zurueck])
            ->assertRedirect($zurueck);
        $this->assertCount(0, Aufgabe::where('titel', 'Beide QQQ')->first()->vorlage->turniere, 'beide angehakt = alle');

        $this->actingAs($leitung)->post(route('aufgaben.store', $a), ['titel' => 'Nur Feb QQQ', 'ressort_id' => $r->id, 'wiederkehrend' => 'jedes_jahr', 'turniere_gesendet' => 1, 'turniere' => [$feb->id]]);
        $this->assertSame([$feb->id], Aufgabe::where('titel', 'Nur Feb QQQ')->first()->vorlage->turniere->pluck('id')->all());

        $this->actingAs($leitung)->post(route('aufgaben.store', $a), ['titel' => 'Ohne QQQ', 'ressort_id' => $r->id, 'wiederkehrend' => 'jedes_jahr', 'turniere_gesendet' => 1])->assertSessionHasErrors('turniere');
        $this->assertNull(Aufgabe::where('titel', 'Ohne QQQ')->first());

        // Fremde Ruecksprung-Adressen werden ignoriert
        $this->actingAs($leitung)->post(route('aufgaben.store', $a), ['titel' => 'Weg QQQ', 'ressort_id' => $r->id, 'zurueck' => '//boese.example'])
            ->assertRedirect(route('aufgaben.show', Aufgabe::where('titel', 'Weg QQQ')->first()));

        // Lesende sehen keinen Knopf
        $this->actingAs($this->person('lesend'))->get(route('ressorts.show', [$r, 'tab' => 'aufgaben']))->assertDontSee('modal-neue-aufgabe', false);
    }

    public function test_bestehende_aufgabe_wie_oft_anpassen(): void
    {
        $a = $this->turnier();
        $r = Ressort::first();
        $dez = Turnier::where('kurz', 'DEZ')->first();
        $feb = Turnier::where('kurz', 'FEB')->first();
        $leitung = $this->person('mitglied', $r->kuerzel, 'leitung');
        $aufgabe = Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'titel' => 'Bestehend QQQ', 'status' => 'offen']);

        // Einmalig -> jedes Jahr, nur Februar (ueber Kasten auf der Aufgabe)
        $this->actingAs($leitung)->patch(route('aufgaben.update', $aufgabe), ['wiederkehrend' => 'jedes_jahr', 'turniere_gesendet' => 1, 'turniere' => [$feb->id]])->assertSessionHasNoErrors();
        $aufgabe->refresh();
        $this->assertSame([$feb->id], $aufgabe->vorlage->turniere->pluck('id')->all());

        // Seite zeigt Auswahl mit Februar angehakt, Dezember nicht
        $html = $this->actingAs($leitung)->get(route('aufgaben.show', $aufgabe))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/value="'.$feb->id.'" checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="'.$dez->id.'" checked/', $html);

        // Anpassen auf beide (= alle), Titel im Bearbeiten-Fenster gleichzeitig aendern
        $this->actingAs($leitung)->patch(route('aufgaben.update', $aufgabe), ['titel' => 'Bestehend neu QQQ', 'wiederkehrend' => 'jedes_jahr', 'turniere_gesendet' => 1, 'turniere' => [$dez->id, $feb->id]])->assertSessionHasNoErrors();
        $this->assertCount(0, $aufgabe->fresh()->vorlage->turniere);
        $this->assertSame('Bestehend neu QQQ', $aufgabe->fresh()->titel);

        // Ohne Haekchen: Fehler; zurueck auf nur dieses Turnier: Jahresplan auf Nein
        $this->actingAs($leitung)->patch(route('aufgaben.update', $aufgabe), ['wiederkehrend' => 'jedes_jahr', 'turniere_gesendet' => 1])->assertSessionHasErrors('turniere');
        $this->actingAs($leitung)->patch(route('aufgaben.update', $aufgabe), ['wiederkehrend' => 'einmalig'])->assertSessionHasNoErrors();
        $this->assertFalse($aufgabe->fresh()->vorlage->aktiv);
        $this->assertSame(1, Vorlage::where('id', $aufgabe->vorlage_id)->count());

        // Mitglied ohne Leitung: keine Auswahl, Aenderung wird ignoriert
        $m = $this->person('mitglied', $r->kuerzel);
        $this->actingAs($m)->get(route('aufgaben.show', $aufgabe))->assertSee('Nur dieses Turnier')->assertDontSee('name="wiederkehrend"', false);
        $this->actingAs($m)->patch(route('aufgaben.update', $aufgabe), ['wiederkehrend' => 'jedes_jahr']);
        $this->assertFalse($aufgabe->fresh()->vorlage->aktiv);
    }
}
