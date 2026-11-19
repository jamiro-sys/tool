<?php

namespace Tests\Feature;

use App\Models\Ausgabe;
use App\Models\Aufgabe;
use App\Models\Erkenntnis;
use App\Models\Turnier;
use App\Models\Vorlage;
use App\Services\AusgabePlaner;
use Carbon\Carbon;

class AusgabeTest extends OkTestCase
{
    public function test_assistent_erstellt_aufgaben_mit_berechneter_faelligkeit(): void
    {
        $admin = $this->person('admin');
        $dez = Turnier::where('kurz', 'DEZ')->first();
        $budget = Vorlage::where('titel', 'Budget erstellen')->first();
        $pokale = Vorlage::where('titel', 'Pokale und Medaillen bestellen')->first();

        $this->actingAs($admin)->get(route('ausgaben.create', ['turnier_id' => $dez->id, 'datum_von' => '2027-12-04']))
            ->assertOk()->assertSee('Budget erstellen');

        $this->actingAs($admin)->post(route('ausgaben.store'), [
            'turnier_id' => $dez->id, 'saison' => '2027/28', 'titel' => 'Test DEZ', 'datum_von' => '2027-12-04',
            'auswahl' => [
                $budget->id => ['aktion' => 'uebernehmen', 'faellig_am' => ''],
                $pokale->id => ['aktion' => 'weglassen'],
            ],
            'zusatz' => [['titel' => 'Neue Idee', 'ressort_id' => $budget->ressort_id]],
        ])->assertRedirect();

        $ausgabe = Ausgabe::where('titel', 'Test DEZ')->firstOrFail();
        $this->assertSame(2, $ausgabe->aufgaben()->count());
        $aufgabe = $ausgabe->aufgaben()->where('vorlage_id', $budget->id)->first();
        $this->assertSame('2027-08-06', $aufgabe->faellig_am->format('Y-m-d')); // 120 Tage vorher
        $this->assertFalse($ausgabe->aufgaben()->where('vorlage_id', $pokale->id)->exists());
    }

    public function test_turnierspezifische_vorlagen_nur_fuer_ihr_turnier(): void
    {
        $planer = app(AusgabePlaner::class);
        $feb = Turnier::where('kurz', 'FEB')->first();
        $titel = $planer->vorschlag($feb, Carbon::parse('2027-02-13'))->map(fn ($e) => $e['vorlage']->titel);
        $this->assertNotContains('Weihnachtsüberraschung für Juniorenteams', $titel);
        $this->assertContains('Spielplan erstellen', $titel);
    }

    public function test_mitglied_darf_keine_ausgabe_erstellen(): void
    {
        $this->actingAs($this->person('mitglied', 'FIN'))->get(route('ausgaben.create'))->assertForbidden();
    }

    public function test_erkenntnis_streichen_deaktiviert_vorlage_und_fehlt_im_naechsten_vorschlag(): void
    {
        $leitung = $this->person('mitglied', 'FW', 'leitung');
        $vorlage = Vorlage::where('titel', 'Hygienekonzept prüfen')->first();
        $e = Erkenntnis::create(['ressort_id' => $vorlage->ressort_id, 'vorlage_id' => $vorlage->id, 'text' => 'Nicht mehr nötig', 'vorschlag' => 'streichen']);

        $this->actingAs($leitung)->post(route('erkenntnisse.uebernehmen', $e))->assertRedirect();

        $this->assertFalse($vorlage->fresh()->aktiv);
        $this->assertNotNull($e->fresh()->umgesetzt_am);
        $this->assertSame(1, $vorlage->versionen()->count());
        $titel = app(AusgabePlaner::class)->vorschlag(Turnier::first(), now()->addYear())->map(fn ($x) => $x['vorlage']->titel);
        $this->assertNotContains('Hygienekonzept prüfen', $titel);
    }

    public function test_erkenntnis_anpassen_aendert_vorlage(): void
    {
        $leitung = $this->person('mitglied', 'SPB', 'leitung');
        $vorlage = Vorlage::where('titel', 'Pokale und Medaillen bestellen')->first();
        $e = Erkenntnis::create(['ressort_id' => $vorlage->ressort_id, 'vorlage_id' => $vorlage->id, 'text' => 'Früher bestellen', 'vorschlag' => 'anpassen']);

        $this->actingAs($leitung)->post(route('erkenntnisse.uebernehmen', $e), ['tage_vorher' => 45])->assertRedirect();
        $this->assertSame(45, $vorlage->fresh()->tage_vorher);
    }

    public function test_einmalige_aufgabe_wird_zur_vorlage(): void
    {
        $leitung = $this->person('mitglied', 'KOM', 'leitung');
        $ausgabe = Ausgabe::create(['turnier_id' => Turnier::first()->id, 'saison' => '2026/27', 'titel' => 'X', 'datum_von' => '2026-12-05']);
        $aufgabe = Aufgabe::create(['ausgabe_id' => $ausgabe->id, 'ressort_id' => $leitung->ressorts->first()->id, 'titel' => 'Programmheft', 'faellig_am' => '2026-11-21']);

        $this->actingAs($leitung)->post(route('erkenntnisse.store'), ['aufgabe_id' => $aufgabe->id, 'text' => 'Jedes Jahr machen', 'vorschlag' => 'neu'])->assertRedirect();
        $this->actingAs($leitung)->post(route('erkenntnisse.uebernehmen', Erkenntnis::first()))->assertRedirect();

        $neu = Vorlage::where('titel', 'Programmheft')->firstOrFail();
        $this->assertSame(14, $neu->tage_vorher);
        $this->assertSame($neu->id, $aufgabe->fresh()->vorlage_id);
    }
}
