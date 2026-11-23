<?php

namespace Tests\Feature;

use App\Models\Aufgabe;
use App\Models\Ausgabe;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\Vorlage;
use App\Services\AusgabePlaner;
use App\Support\Saison;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

/** Dezember und Februar gehoeren zu einer Saison; Aufgaben einmal pro Saison. */
class SaisonTest extends OkTestCase
{
    protected function dez(): Turnier
    {
        return Turnier::where('kurz', 'DEZ')->firstOrFail();
    }

    protected function feb(): Turnier
    {
        return Turnier::where('kurz', 'FEB')->firstOrFail();
    }

    /** Turnier ueber den normalen Assistenten anlegen, alle Vorschlaege uebernehmen. */
    protected function anlegen($wer, Turnier $t, string $von, string $saison)
    {
        $vorschlag = app(AusgabePlaner::class)->vorschlag($t, Carbon::parse($von), null, $saison);
        $auswahl = $vorschlag->mapWithKeys(fn ($e) => [$e['vorlage']->id => ['aktion' => 'uebernehmen']])->all();

        return $this->actingAs($wer)->post(route('ausgaben.store'), [
            'turnier_id' => $t->id, 'saison' => $saison, 'titel' => $t->name.' '.$saison, 'datum_von' => $von, 'auswahl' => $auswahl,
        ]);
    }

    public function test_saison_rechnung(): void
    {
        $this->assertSame('2027/28', Saison::fuer(Carbon::parse('2027-12-04')));
        $this->assertSame('2027/28', Saison::fuer(Carbon::parse('2028-02-12')));
        $this->assertSame('2027/28', Saison::fuer(Carbon::parse('2027-07-01')));
        $this->assertSame('2026/27', Saison::fuer(Carbon::parse('2027-06-30')));
        $this->assertSame('2028/29', Saison::naechste('2027/28'));
        $this->assertSame('2099/00', Saison::naechste('2098/99'));
    }

    public function test_neue_saison_in_einem_ablauf(): void
    {
        Notification::fake();
        $admin = $this->person('admin');
        Ausgabe::create(['turnier_id' => $this->dez()->id, 'saison' => '2026/27', 'titel' => 'Dez 26', 'datum_von' => '2026-12-05', 'datum_bis' => '2026-12-06', 'status' => 'planung']);
        Ausgabe::create(['turnier_id' => $this->feb()->id, 'saison' => '2026/27', 'titel' => 'Feb 27', 'datum_von' => '2027-02-13', 'datum_bis' => '2027-02-14', 'status' => 'planung']);

        // Nur Admin, Vorschlag: naechste Saison, gleicher Wochentag ein Jahr spaeter
        $this->actingAs($this->person('mitglied'))->get(route('saison.create'))->assertForbidden();
        $this->actingAs($admin)->get(route('saison.create'))->assertOk()
            ->assertSee('value="2027/28"', false)->assertSee('value="2027-12-04"', false)->assertSee('value="2028-02-12"', false);

        // Datum ausserhalb der Saison wird abgelehnt
        $this->actingAs($admin)->post(route('saison.store'), ['saison' => '2027/28', 'turniere' => [
            $this->dez()->id => ['mit' => 1, 'datum_von' => '2028-12-02'],
        ]])->assertSessionHasErrors('turniere.'.$this->dez()->id.'.datum_von');

        // Februar zuerst eingegeben, trotzdem fuehrt der Ablauf zuerst durch Dezember
        $antwort = $this->actingAs($admin)->post(route('saison.store'), ['saison' => '2027/28', 'turniere' => [
            $this->feb()->id => ['mit' => 1, 'datum_von' => '2028-02-12', 'datum_bis' => '2028-02-13'],
            $this->dez()->id => ['mit' => 1, 'datum_von' => '2027-12-04', 'datum_bis' => '2027-12-05'],
        ]]);
        $antwort->assertRedirect(route('ausgaben.create', ['turnier_id' => $this->dez()->id, 'datum_von' => '2027-12-04', 'datum_bis' => '2027-12-05', 'saison' => '2027/28']));
        $this->actingAs($admin)->get(route('ausgaben.create', ['turnier_id' => $this->dez()->id, 'datum_von' => '2027-12-04', 'datum_bis' => '2027-12-05', 'saison' => '2027/28']))
            ->assertOk()->assertSee('Turnier 1 von 2')->assertSee('Saison 2027/28 planen');

        $this->anlegen($admin, $this->dez(), '2027-12-04', '2027/28')
            ->assertRedirect(route('ausgaben.create', ['turnier_id' => $this->feb()->id, 'datum_von' => '2028-02-12', 'datum_bis' => '2028-02-13', 'saison' => '2027/28']));
        $this->anlegen($admin, $this->feb(), '2028-02-12', '2027/28')->assertRedirect(route('ausgaben.index'));

        $this->assertSame(2, Ausgabe::where('saison', '2027/28')->count());
        $this->assertNull(session(\App\Http\Controllers\SaisonController::SESSION));
        $this->actingAs($admin)->get(route('ausgaben.index'))->assertOk()->assertSeeInOrder(['2027/28', '2026/27']);
    }

    public function test_aufgabe_einmal_pro_saison_nur_im_ersten_turnier(): void
    {
        $admin = $this->person('admin');
        $r = Ressort::first();
        $saison = Vorlage::create(['ressort_id' => $r->id, 'titel' => 'Sponsoren anfragen QQ', 'tage_vorher' => 120, 'aktiv' => true, 'haeufigkeit' => 'saison']);
        $jedes = Vorlage::create(['ressort_id' => $r->id, 'titel' => 'Halle schmuecken QQ', 'tage_vorher' => 1, 'aktiv' => true]);

        // Februar zuerst angelegt: Saison-Aufgabe landet dort, Dezember bekommt sie nicht nochmals
        $this->anlegen($admin, $this->feb(), '2028-02-12', '2027/28');
        $this->anlegen($admin, $this->dez(), '2027-12-04', '2027/28');
        $feb = Ausgabe::where('turnier_id', $this->feb()->id)->where('saison', '2027/28')->firstOrFail();
        $dez = Ausgabe::where('turnier_id', $this->dez()->id)->where('saison', '2027/28')->firstOrFail();

        $this->assertSame(1, Aufgabe::where('vorlage_id', $saison->id)->count());
        $this->assertTrue(Aufgabe::where('vorlage_id', $saison->id)->where('ausgabe_id', $feb->id)->value('saison_aufgabe'));
        $this->assertSame(2, Aufgabe::where('vorlage_id', $jedes->id)->count());

        // Im Dezember erscheint sie im Block "Fuer die ganze Saison"
        $this->actingAs($admin)->get(route('aufgaben.index', $dez))->assertOk()->assertSee('Für die ganze Saison 2027/28')->assertSee('Sponsoren anfragen QQ');
        $this->actingAs($admin)->get(route('ausgaben.show', $dez))->assertOk()->assertSee('1 Aufgabe für die ganze Saison offen');

        // Neue Saison: wieder genau einmal
        $this->anlegen($admin, $this->dez(), '2028-12-02', '2028/29');
        $this->assertSame(2, Aufgabe::where('vorlage_id', $saison->id)->count());
    }

    public function test_jahresplan_und_aufgabe_einmal_pro_saison_speichern(): void
    {
        $r = Ressort::first();
        $leitung = $this->person('mitglied', $r->kuerzel, 'leitung');
        $this->actingAs($leitung)->post(route('vorlagen.store'), ['ressort_id' => $r->id, 'titel' => 'Budget Saison QQ', 'tage_vorher' => 90, 'prioritaet' => 'normal',
            'haeufigkeit' => 'saison', 'turniere_gesendet' => 1, 'turniere' => [$this->dez()->id]])->assertSessionHasNoErrors();
        $v = Vorlage::where('titel', 'Budget Saison QQ')->firstOrFail();
        $this->assertTrue($v->proSaison());
        $this->assertTrue($v->turniere->isEmpty());

        // Export und Import nehmen "einmal pro Saison" mit
        $admin = $this->person('admin');
        $json = $this->actingAs($admin)->get(route('admin.daten.vorlagen'))->streamedContent();
        $this->assertStringContainsString('"haeufigkeit": "saison"', $json);
        $v->delete();
        $this->actingAs($admin)->post(route('admin.daten.vorlagen'), ['datei' => \Illuminate\Http\UploadedFile::fake()->createWithContent('j.json', $json)]);
        $this->assertTrue(Vorlage::where('titel', 'Budget Saison QQ')->firstOrFail()->proSaison());

        $a = Ausgabe::create(['turnier_id' => $this->dez()->id, 'saison' => '2026/27', 'titel' => 'Dez QQ', 'datum_von' => today()->addDays(40), 'status' => 'planung']);
        $this->actingAs($leitung)->post(route('aufgaben.store', $a), ['titel' => 'Plakat beide Daten QQ', 'ressort_id' => $r->id, 'wiederkehrend' => 'jedes_jahr', 'haeufigkeit' => 'saison'])->assertSessionHasNoErrors();
        $aufgabe = Aufgabe::where('titel', 'Plakat beide Daten QQ')->firstOrFail();
        $this->assertTrue($aufgabe->saison_aufgabe);
        $this->assertSame('saison', $aufgabe->vorlage->haeufigkeit);
        $this->actingAs($leitung)->get(route('aufgaben.show', $aufgabe))->assertSee('gilt für die ganze Saison 2026/27');
    }

    public function test_erinnerung_fuer_saison_aufgabe_solange_saison_laeuft(): void
    {
        Notification::fake();
        $r = Ressort::first();
        $m = $this->person('mitglied', $r->kuerzel);
        $dez = Ausgabe::create(['turnier_id' => $this->dez()->id, 'saison' => '2030/31', 'titel' => 'Dez lange vorbei', 'datum_von' => today()->subDays(80), 'status' => 'rueckblick']);
        Ausgabe::create(['turnier_id' => $this->feb()->id, 'saison' => '2030/31', 'titel' => 'Feb bald', 'datum_von' => today()->addDays(10), 'status' => 'planung']);
        Aufgabe::create(['ausgabe_id' => $dez->id, 'ressort_id' => $r->id, 'titel' => 'Saison QQ', 'status' => 'offen', 'saison_aufgabe' => true, 'verantwortlich_id' => $m->id, 'faellig_am' => today()->subDay()]);
        Aufgabe::create(['ausgabe_id' => $dez->id, 'ressort_id' => $r->id, 'titel' => 'Nur Dez QQ', 'status' => 'offen', 'verantwortlich_id' => $m->id, 'faellig_am' => today()->subDay()]);

        $this->artisan('ok:erinnerungen')->assertSuccessful();
        $this->assertNotNull(Aufgabe::where('titel', 'Saison QQ')->value('erinnert_am'));
        $this->assertNull(Aufgabe::where('titel', 'Nur Dez QQ')->value('erinnert_am'));
    }

    public function test_turnierart_name_und_kuerzel_eindeutig(): void
    {
        $admin = $this->person('admin');
        $this->actingAs($admin)->post(route('admin.turniere.speichern'), ['name' => 'Hallencup Februar', 'kurz' => 'XYZ'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('admin.turniere.speichern'), ['name' => 'Hallencup Ostern', 'kurz' => 'FEB'])->assertSessionHasErrors('kurz');
        $this->actingAs($admin)->post(route('admin.turniere.speichern'), ['name' => 'Hallencup Ostern', 'kurz' => 'OST'])->assertSessionHasNoErrors();
    }
}
