<?php

namespace Tests\Feature;

use App\Models\Ausgabe;
use App\Models\Material;
use App\Models\MaterialEinsatz;
use App\Models\Turnier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class MaterialTest extends OkTestCase
{
    private function ausgabe(int $tage): Ausgabe
    {
        return Ausgabe::create(['turnier_id' => Turnier::first()->id, 'saison' => 'S'.$tage, 'titel' => 'Ausgabe '.$tage, 'datum_von' => today()->addDays($tage), 'status' => 'planung']);
    }

    public function test_inventar_mit_foto(): void
    {
        Storage::fake('local');
        $m = $this->person('mitglied');
        $this->actingAs($m)->get(route('material.index'))->assertOk()->assertSee('Noch kein Material');
        $this->actingAs($m)->post(route('material.speichern'), ['name' => 'Festbank', 'menge' => 12, 'zustand' => 'gut', 'eigentum' => 'verein', 'foto' => UploadedFile::fake()->image('bank.jpg')])->assertSessionHasNoErrors();
        $material = Material::first();
        $this->assertNotNull($material->foto_pfad);
        $this->actingAs($m)->get(route('material.foto', $material))->assertOk();
        $this->actingAs($m)->post(route('material.speichern'), ['name' => 'X', 'zustand' => 'kaputt', 'eigentum' => 'verein'])->assertSessionHasErrors('zustand');
        $this->actingAs($this->person('lesend'))->post(route('material.speichern'), ['name' => 'X', 'zustand' => 'gut', 'eigentum' => 'verein'])->assertForbidden();
    }

    public function test_einsatzliste_haken_und_uebernahme(): void
    {
        $alt = $this->ausgabe(-180);
        $neu = $this->ausgabe(20);
        $material = Material::create(['name' => 'Festbank', 'menge' => 12]);
        $m = $this->person('mitglied');

        $this->actingAs($m)->post(route('material.einsatz', $alt), ['material_id' => $material->id])->assertSessionHasNoErrors();
        $e = MaterialEinsatz::first();
        $this->assertSame(12.0, $e->menge);
        $this->actingAs($m)->postJson(route('material.haken', [$alt, $e]), ['feld' => 'aufgebaut'])->assertOk()->assertJson(['wert' => true]);
        $this->actingAs($m)->postJson(route('material.haken', [$alt, $e]), ['feld' => 'loeschen'])->assertUnprocessable();

        $this->actingAs($m)->post(route('material.uebernehmen', $neu));
        $this->actingAs($m)->post(route('material.uebernehmen', $neu));
        $kopie = $neu->material()->first();
        $this->assertSame(1, $neu->material()->count());
        $this->assertFalse($kopie->aufgebaut);

        $this->actingAs($m)->get(route('material.ausgabe', $neu))->assertOk()->assertSee('Festbank');
        $this->actingAs($m)->postJson(route('material.haken', [$neu, $e]), ['feld' => 'abgebaut'])->assertNotFound();
    }

    public function test_erinnerung_an_rueckgabe(): void
    {
        Notification::fake();
        $a = $this->ausgabe(-1);
        $person = $this->person('mitglied');
        $material = Material::create(['name' => 'Beamer', 'eigentum' => 'geliehen']);
        MaterialEinsatz::create(['ausgabe_id' => $a->id, 'material_id' => $material->id, 'rueckgabe_bis' => today()->addDay(), 'verantwortlich_id' => $person->id, 'herkunft' => 'geliehen', 'quelle' => 'Schule']);

        $this->artisan('ok:erinnerungen')->assertSuccessful();
        $this->artisan('ok:erinnerungen')->assertSuccessful();
        Notification::assertSentToTimes($person, \App\Notifications\Hinweis::class, 1);
    }

    public function test_verlauf_zeigt_wer_mitgebracht_hat_und_kauf_pruefen(): void
    {
        $luca = $this->person('mitglied');
        $luca->update(['name' => 'Luca Frei']);
        $beamer = Material::create(['name' => 'Beamer', 'ressort_id' => \App\Models\Ressort::first()->id, 'zweck' => 'Rangliste im Turnierbüro']);
        $boxen = Material::create(['name' => 'Lautsprecher']);
        foreach ([-720, -540, -360] as $i => $tage) {
            $a = $this->ausgabe($tage);
            MaterialEinsatz::create(['ausgabe_id' => $a->id, 'material_id' => $beamer->id, 'herkunft' => 'mitgebracht', 'quelle_user_id' => $luca->id]);
            MaterialEinsatz::create(['ausgabe_id' => $a->id, 'material_id' => $boxen->id, 'herkunft' => 'gemietet', 'quelle' => 'Musik Meier', 'kosten' => 150]);
        }

        $seite = $this->actingAs($this->person('mitglied'))->get(route('material.index'))->assertOk();
        $seite->assertSee('Mitgebracht von Luca Frei')->assertSee('Gemietet bei Musik Meier')->assertSee('Kauf prüfen')->assertSee("3x gemietet, zusammen CHF 450.00", false);
        $this->assertTrue($boxen->load('einsaetze')->mietBilanz()['pruefen']);
        $this->assertFalse($beamer->load('einsaetze')->mietBilanz()['pruefen']);

        $this->actingAs($this->person('mitglied'))->get(route('material.index', ['q' => 'beamer']))->assertSee('Beamer')->assertDontSee('Lautsprecher');
        $this->actingAs($this->person('mitglied'))->get(route('ressorts.show', \App\Models\Ressort::first()))->assertSee('Rangliste im Turnierbüro');

        \App\Models\Einstellung::setze('anschaffung_ab', 0);
        $this->assertFalse($boxen->fresh()->load('einsaetze')->mietBilanz()['pruefen']);
    }

    public function test_neuer_einsatz_uebernimmt_eigentum_als_herkunft(): void
    {
        $a = $this->ausgabe(10);
        $m = Material::create(['name' => 'Tore', 'eigentum' => 'halle']);
        $this->actingAs($this->person('mitglied'))->post(route('material.einsatz', $a), ['material_id' => $m->id])->assertSessionHasNoErrors();
        $this->assertSame('halle', MaterialEinsatz::first()->herkunft);
        $e = MaterialEinsatz::first();
        $this->actingAs($this->person('mitglied'))->post(route('material.einsatz', [$a, $e]), ['herkunft' => 'gemietet', 'kosten' => '80', 'quelle' => 'Sport AG'])->assertSessionHasNoErrors();
        $this->assertSame('Gemietet bei Sport AG', $e->fresh()->herkunftText());
    }
}
