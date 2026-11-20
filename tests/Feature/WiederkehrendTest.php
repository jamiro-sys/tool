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
        $this->actingAs($leitung)->get(route('aufgaben.show', $aufgabe))->assertSee('Jedes Turnier wiederholen');
        $this->actingAs($leitung)->post(route('aufgaben.wiederholen', $aufgabe))->assertRedirect();

        $vorlage = Vorlage::where('titel', 'Beamer reservieren XYZ')->firstOrFail();
        $this->assertTrue($vorlage->aktiv);
        $this->assertSame(10, $vorlage->tage_vorher);
        $this->assertSame(0.5, $vorlage->aufwand_std);
        $this->assertSame($vorlage->id, $aufgabe->fresh()->vorlage_id);

        $this->actingAs($leitung)->get(route('aufgaben.show', $aufgabe))->assertSee('Wiederkehrend')->assertDontSee('Jedes Turnier wiederholen');
        $this->actingAs($leitung)->post(route('aufgaben.wiederholen', $aufgabe))->assertStatus(409);
    }

    public function test_vorlage_wiederkehrend_ja_nein(): void
    {
        $admin = $this->person('admin');
        $vorlage = Vorlage::create(['ressort_id' => Ressort::first()->id, 'titel' => 'Halle buchen XYZ', 'tage_vorher' => 90]);
        $this->actingAs($admin)->get(route('vorlagen.show', $vorlage))->assertOk()->assertSee('Wiederkehrend');
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
}
