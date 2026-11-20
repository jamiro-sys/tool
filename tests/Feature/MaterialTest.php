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
        MaterialEinsatz::create(['ausgabe_id' => $a->id, 'material_id' => $material->id, 'rueckgabe_bis' => today()->addDay(), 'verantwortlich_id' => $person->id, 'ausleihe' => 'Schule']);

        $this->artisan('ok:erinnerungen')->assertSuccessful();
        $this->artisan('ok:erinnerungen')->assertSuccessful();
        Notification::assertSentToTimes($person, \App\Notifications\Hinweis::class, 1);
    }
}
