<?php

namespace Tests\Feature;

use App\Models\Aufgabe;
use App\Models\Ausgabe;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\Vorlage;
use App\Services\AusgabePlaner;
use App\Support\Wann;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WannTest extends OkTestCase
{
    private function turnier(string $von = '2026-12-05', ?string $bis = '2026-12-06', string $kurz = 'DEZ'): Ausgabe
    {
        return Ausgabe::create(['turnier_id' => Turnier::where('kurz', $kurz)->first()->id, 'saison' => '2026/27', 'titel' => 'Cup '.$kurz, 'datum_von' => $von, 'datum_bis' => $bis, 'status' => 'planung']);
    }

    public function test_rechnung_und_texte(): void
    {
        $a = $this->turnier();
        $this->assertSame('2026-11-21', Wann::fuer($a, 14)->toDateString());
        $this->assertSame('2026-12-05', Wann::fuer($a, 0)->toDateString());
        $this->assertSame('2026-12-06', Wann::fuer($a, 0, 'ende')->toDateString());
        $this->assertSame('2026-12-09', Wann::fuer($a, -3, 'ende')->toDateString());
        $eintaegig = $this->turnier('2027-02-13', null, 'FEB');
        $this->assertSame('2027-02-16', Wann::fuer($eintaegig, -3, 'ende')->toDateString(), 'ohne Enddatum zaehlt der erste Tag');

        $this->assertSame('2 Wochen vor Turnierbeginn', Wann::text(14));
        $this->assertSame('am ersten Turniertag', Wann::text(0));
        $this->assertSame('am letzten Turniertag', Wann::text(0, 'ende'));
        $this->assertSame('3 Tage nach Turnierende', Wann::text(-3, 'ende'));
        $this->assertSame('1 Monat vorher', Wann::kurz(30));
        $this->assertSame('1 Tag nachher', Wann::kurz(-1, 'ende'));
        $this->assertSame(14, Wann::ausDatum($a, Carbon::parse('2026-11-21')));
    }

    public function test_aufgabe_mit_abstand_fix_und_ohne(): void
    {
        $a = $this->turnier();
        $r = Ressort::first();
        $admin = $this->person('admin');
        $basis = ['ressort_id' => $r->id, 'wann_gesendet' => 1];

        $this->actingAs($admin)->get(route('aufgaben.index', $a))->assertSee('vor Turnierbeginn')->assertSee('nach Turnierende')->assertSee('fixes Datum');

        $this->actingAs($admin)->post(route('aufgaben.store', $a), $basis + ['titel' => 'Vorher QQ', 'tage_vorher' => 14, 'bezug' => 'beginn'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('aufgaben.store', $a), $basis + ['titel' => 'Nachher QQ', 'tage_vorher' => -2, 'bezug' => 'ende'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('aufgaben.store', $a), $basis + ['titel' => 'Fix QQ', 'tage_vorher' => '', 'bezug' => 'beginn', 'faellig_am' => '2026-10-01'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('aufgaben.store', $a), $basis + ['titel' => 'Ohne QQ', 'tage_vorher' => '', 'bezug' => 'beginn'])->assertSessionHasNoErrors();

        $hol = fn ($t) => Aufgabe::where('titel', $t)->first();
        $this->assertSame(['2026-11-21', 14, 'beginn'], [$hol('Vorher QQ')->faellig_am->toDateString(), $hol('Vorher QQ')->tage_vorher, $hol('Vorher QQ')->bezug]);
        $this->assertSame(['2026-12-08', -2, 'ende'], [$hol('Nachher QQ')->faellig_am->toDateString(), $hol('Nachher QQ')->tage_vorher, $hol('Nachher QQ')->bezug]);
        $this->assertSame(['2026-10-01', null], [$hol('Fix QQ')->faellig_am->toDateString(), $hol('Fix QQ')->tage_vorher]);
        $this->assertNull($hol('Ohne QQ')->faellig_am);

        $this->actingAs($admin)->get(route('aufgaben.show', $hol('Nachher QQ')))->assertSee('2 Tage nach Turnierende');
        $this->actingAs($admin)->get(route('aufgaben.show', $hol('Fix QQ')))->assertSee('fixes Datum');

        // Turnier verschiebt sich um eine Woche: relative wandern mit, fixe nur mit Schalter
        $this->actingAs($admin)->patch(route('ausgaben.update', $a), ['datum_von' => '2026-12-12', 'datum_bis' => '2026-12-13'])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-28', $hol('Vorher QQ')->faellig_am->toDateString());
        $this->assertSame('2026-12-15', $hol('Nachher QQ')->faellig_am->toDateString());
        $this->assertSame('2026-10-01', $hol('Fix QQ')->faellig_am->toDateString());
        $this->actingAs($admin)->patch(route('ausgaben.update', $a), ['datum_von' => '2026-12-19', 'datum_bis' => '2026-12-20', 'faelligkeiten_verschieben' => 1]);
        $this->assertSame('2026-10-08', $hol('Fix QQ')->faellig_am->toDateString());
        $this->assertSame('2026-12-05', $hol('Vorher QQ')->faellig_am->toDateString());

        // Nur das Enddatum aendert sich: "nach Turnierende" wandert, "vor Beginn" nicht
        $this->actingAs($admin)->patch(route('ausgaben.update', $a), ['datum_bis' => '2026-12-21']);
        $this->assertSame('2026-12-23', $hol('Nachher QQ')->faellig_am->toDateString());
        $this->assertSame('2026-12-05', $hol('Vorher QQ')->faellig_am->toDateString());
    }

    public function test_aenderung_auch_im_jahresplan_oder_nur_hier(): void
    {
        $a = $this->turnier();
        $r = Ressort::first();
        $admin = $this->person('admin');
        $v = Vorlage::create(['ressort_id' => $r->id, 'titel' => 'Stockgeld QQ', 'tage_vorher' => 14, 'aufwand_std' => 1, 'aktiv' => true]);
        $aufgabe = Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'vorlage_id' => $v->id, 'titel' => 'Stockgeld QQ', 'status' => 'offen', 'tage_vorher' => 14, 'bezug' => 'beginn', 'faellig_am' => '2026-11-21']);

        $this->actingAs($admin)->get(route('aufgaben.show', $aufgabe))->assertSee('Auch im Jahresplan übernehmen');

        // Ausnahme nur fuer dieses Turnier
        $this->actingAs($admin)->patch(route('aufgaben.update', $aufgabe), ['titel' => 'Stockgeld QQ', 'wann_gesendet' => 1, 'tage_vorher' => 7, 'bezug' => 'beginn', 'jahresplan_mit' => 0])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-28', $aufgabe->fresh()->faellig_am->toDateString());
        $this->assertSame(14, $v->fresh()->tage_vorher);

        // Mit Haekchen: Jahresplan geht mit, inkl. Verlauf
        $this->actingAs($admin)->patch(route('aufgaben.update', $aufgabe), ['titel' => 'Stockgeld bestellen QQ', 'wann_gesendet' => 1, 'tage_vorher' => -1, 'bezug' => 'ende', 'aufwand_geplant' => 0.5, 'jahresplan_mit' => 1])->assertSessionHasNoErrors();
        $v->refresh();
        $this->assertSame(['Stockgeld bestellen QQ', -1, 'ende', 0.5], [$v->titel, $v->tage_vorher, $v->bezug, $v->aufwand_std]);
        $this->assertTrue($v->versionen()->where('notiz', 'like', 'Von Aufgabe übernommen%')->exists());

        // Fixes Datum wird fuer den Jahresplan in Abstand zum Beginn umgerechnet
        $this->actingAs($admin)->patch(route('aufgaben.update', $aufgabe), ['titel' => 'Stockgeld bestellen QQ', 'wann_gesendet' => 1, 'tage_vorher' => '', 'bezug' => 'beginn', 'faellig_am' => '2026-11-05', 'jahresplan_mit' => 1]);
        $this->assertSame([30, 'beginn'], [$v->fresh()->tage_vorher, $v->fresh()->bezug]);
    }

    public function test_assistent_und_jahresplan_mit_bezug_ende(): void
    {
        $r = Ressort::first();
        $v = Vorlage::create(['ressort_id' => $r->id, 'titel' => 'Abrechnung QQ', 'tage_vorher' => -7, 'bezug' => 'ende', 'aktiv' => true]);
        $planer = app(AusgabePlaner::class);
        $dez = Turnier::where('kurz', 'DEZ')->first();
        $feb = Turnier::where('kurz', 'FEB')->first();

        $eintrag = fn (Turnier $t, string $von, string $bis) => $planer->vorschlag($t, Carbon::parse($von), Carbon::parse($bis))->first(fn ($e) => $e['vorlage']->id === $v->id);
        $this->assertSame('2026-12-13', $eintrag($dez, '2026-12-05', '2026-12-06')['faellig_am']->toDateString());
        $this->assertSame('2027-02-21', $eintrag($feb, '2027-02-13', '2027-02-14')['faellig_am']->toDateString());

        $ausgabe = $planer->erstelle($feb, ['datum_von' => '2027-02-13', 'datum_bis' => '2027-02-14', 'saison' => '2026/27'], [$v->id => ['aktion' => 'uebernehmen']]);
        $aufgabe = $ausgabe->aufgaben()->where('vorlage_id', $v->id)->first();
        $this->assertSame(['2027-02-21', -7, 'ende'], [$aufgabe->faellig_am->toDateString(), $aufgabe->tage_vorher, $aufgabe->bezug]);

        // Im Assistenten ein eigenes Datum gesetzt: Abstand zum Beginn wird daraus berechnet
        $ausgabe2 = $planer->erstelle($dez, ['datum_von' => '2027-12-04', 'datum_bis' => '2027-12-05', 'saison' => '2027/28'], [$v->id => ['aktion' => 'uebernehmen', 'faellig_am' => '2027-12-20']]);
        $a2 = $ausgabe2->aufgaben()->where('vorlage_id', $v->id)->first();
        $this->assertSame(['2027-12-20', -16, 'beginn'], [$a2->faellig_am->toDateString(), $a2->tage_vorher, $a2->bezug]);
    }

    public function test_migration_fuellt_bestehende_aufgaben(): void
    {
        $a = $this->turnier();
        $aufgabe = Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => Ressort::first()->id, 'titel' => 'Alt QQ', 'status' => 'offen', 'faellig_am' => '2026-11-28']);
        $migration = require database_path('migrations/2026_11_20_000800_faelligkeit_relativ.php');
        $migration->down();
        $migration->up();
        $row = DB::table('aufgaben')->where('id', $aufgabe->id)->first();
        $this->assertSame([7, 'beginn', '2026-11-28'], [(int) $row->tage_vorher, $row->bezug, substr($row->faellig_am, 0, 10)]);
    }

    public function test_nachher_wird_ab_turnierende_gezaehlt(): void
    {
        $a = $this->turnier('2026-12-05', '2026-12-06');
        $r = Ressort::first();
        $v = Vorlage::create(['ressort_id' => $r->id, 'titel' => 'Abrechnung QQ', 'tage_vorher' => -14, 'bezug' => 'beginn', 'aktiv' => true]);
        $nachher = Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'vorlage_id' => $v->id, 'titel' => 'Abrechnung QQ', 'status' => 'offen', 'faellig_am' => '2026-12-19', 'tage_vorher' => -14, 'bezug' => 'beginn']);
        $amTag2 = Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'titel' => 'Abbau QQ', 'status' => 'offen', 'faellig_am' => '2026-12-06', 'tage_vorher' => -1, 'bezug' => 'beginn']);
        $vorher = Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'titel' => 'Vorher QQ', 'status' => 'offen', 'faellig_am' => '2026-11-21', 'tage_vorher' => 14, 'bezug' => 'beginn']);

        $migration = require database_path('migrations/2026_11_20_000900_nachher_ab_turnierende.php');
        $migration->up();

        $nachher->refresh();
        $this->assertSame([-13, 'ende', '2026-12-19'], [$nachher->tage_vorher, $nachher->bezug, $nachher->faellig_am->toDateString()]);
        $this->assertSame('13 Tage nach Turnierende', $nachher->wannText());
        $this->assertSame([0, 'ende', '2026-12-06'], [$amTag2->fresh()->tage_vorher, $amTag2->fresh()->bezug, $amTag2->fresh()->faellig_am->toDateString()]);
        $this->assertSame('am letzten Turniertag', $amTag2->fresh()->wannText());
        $this->assertSame([14, 'beginn'], [$vorher->fresh()->tage_vorher, $vorher->fresh()->bezug]);
        $this->assertSame([-13, 'ende'], [$v->fresh()->tage_vorher, $v->fresh()->bezug]);

        // Datum bleibt gleich, auch beim Neuberechnen
        $nachher->load('ausgabe')->rechneFaelligkeit();
        $this->assertSame('2026-12-19', $nachher->faellig_am->toDateString());
    }
}
