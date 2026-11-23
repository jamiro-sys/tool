<?php

namespace Tests\Feature;

use App\Models\Ausgabe;
use App\Models\Dokument;
use App\Models\Ordner;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\Vorlage;
use App\Services\AusgabePlaner;
use App\Services\Inhalte;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrdnerUndVorschauTest extends OkTestCase
{
    public function test_ordner_anlegen_verschieben_umbenennen_loeschen(): void
    {
        Storage::fake('local');
        [$r1, $r2] = Ressort::orderBy('sortierung')->take(2)->get()->all();
        $m = $this->person('mitglied', $r1->kuerzel);
        $m->ressorts()->attach($r2->id, ['rolle' => 'mitglied']);
        $m = $m->fresh();

        // Wer nicht im Ressort mitarbeitet, kann dort nichts ablegen, verschieben oder Ordner aendern
        $fremd = $this->person('mitglied', $r1->kuerzel);
        $this->actingAs($fremd)->post(route('ordner.speichern'), ['ressort_id' => $r2->id, 'name' => 'Fremd QQQ'])->assertForbidden();
        $this->actingAs($fremd)->post(route('dateien.speichern'), ['ressort_id' => $r2->id, 'url' => 'https://example.ch'])->assertForbidden();
        $this->actingAs($fremd)->post(route('dateien.speichern'), ['url' => 'https://example.ch', 'titel' => 'Allgemein QQQ'])->assertSessionHasNoErrors();

        // Beim Hochladen neuen Unterordner anlegen
        $this->actingAs($m)->post(route('dateien.speichern'), ['ressort_id' => $r1->id, 'ordner_id' => 'neu', 'neuer_ordner' => 'Verträge QQQ',
            'dateien' => [UploadedFile::fake()->create('Mietvertrag Halle.pdf', 50, 'application/pdf')]])->assertSessionHasNoErrors();
        $ordner = Ordner::where('name', 'Verträge QQQ')->firstOrFail();
        $this->assertSame($r1->id, $ordner->ressort_id);
        $vertrag = Dokument::where('titel', 'Mietvertrag Halle')->firstOrFail();
        $this->assertSame($ordner->id, $vertrag->ordner_id);

        // Ordner eines anderen Ressorts wird abgelehnt, ohne Name auch
        $this->actingAs($m)->post(route('dateien.speichern'), ['ressort_id' => $r2->id, 'ordner_id' => (string) $ordner->id, 'url' => 'https://example.ch'])->assertSessionHasErrors('ordner_id');
        $this->actingAs($m)->post(route('dateien.speichern'), ['ressort_id' => $r1->id, 'ordner_id' => 'neu', 'url' => 'https://example.ch'])->assertSessionHasErrors('neuer_ordner');

        // Ordneransicht: Ressort-Ordner, Unterordner, Eintrag
        $this->actingAs($m)->get(route('dateien.index'))->assertOk()->assertSee('Allgemein')->assertSee($r1->name)->assertSee('Verträge QQQ')->assertSee('Mietvertrag Halle');
        $this->actingAs($m)->get(route('ressorts.show', [$r1, 'tab' => 'dateien']))->assertOk()->assertSee('Verträge QQQ')->assertSee('Mietvertrag Halle');
        $this->actingAs($m)->get(route('dateien.index', ['q' => 'miet']))->assertSee($r1->name.' / Verträge QQQ');

        // Leerer Unterordner direkt anlegen, Datei dorthin verschieben (anderes Ressort)
        $this->actingAs($m)->post(route('ordner.speichern'), ['ressort_id' => $r2->id, 'name' => 'Pläne QQQ'])->assertSessionHasNoErrors();
        $plaene = Ordner::where('name', 'Pläne QQQ')->firstOrFail();
        $this->actingAs($m)->patch(route('dateien.aendern', $vertrag), ['titel' => 'Mietvertrag', 'ort' => 'o:'.$plaene->id])->assertSessionHasNoErrors();
        $this->assertSame([$r2->id, $plaene->id], [$vertrag->fresh()->ressort_id, $vertrag->fresh()->ordner_id]);
        $this->actingAs($m)->patch(route('dateien.aendern', $vertrag), ['titel' => 'Mietvertrag', 'ort' => 'r:0'])->assertSessionHasNoErrors();
        $this->assertSame([null, null], [$vertrag->fresh()->ressort_id, $vertrag->fresh()->ordner_id]);
        $this->actingAs($m)->patch(route('dateien.aendern', $vertrag), ['titel' => 'Mietvertrag', 'ort' => 'o:'.$plaene->id])->assertSessionHasNoErrors();

        // Umbenennen (doppelt geht nicht), loeschen: Inhalt bleibt im Ressort
        $this->actingAs($m)->post(route('ordner.speichern'), ['ressort_id' => $r2->id, 'name' => 'Belege QQQ']);
        $this->actingAs($m)->patch(route('ordner.aendern', $plaene), ['name' => 'Belege QQQ'])->assertSessionHasErrors('name');
        $this->actingAs($m)->patch(route('ordner.aendern', $plaene), ['name' => 'Hallenpläne QQQ'])->assertSessionHasNoErrors();
        $this->assertSame('Hallenpläne QQQ', $plaene->fresh()->name);
        $this->actingAs($m)->delete(route('ordner.loeschen', $plaene))->assertSessionHasNoErrors();
        $this->assertNull(Ordner::find($plaene->id));
        $this->assertSame([$r2->id, null], [$vertrag->fresh()->ressort_id, $vertrag->fresh()->ordner_id]);
        $this->actingAs($fremd)->patch(route('dateien.aendern', $vertrag), ['titel' => 'Weg', 'ort' => 'r:'.$r1->id])->assertForbidden();
        $this->actingAs($fremd)->get(route('dokumente.download', [$vertrag, 'ansehen' => 1]))->assertOk();

        // Lesende Person darf nichts anlegen
        $this->actingAs($this->person('lesend'))->post(route('ordner.speichern'), ['name' => 'X'])->assertForbidden();

        // Leeren entfernt auch Ordner
        app(Inhalte::class)->leeren();
        $this->assertSame(0, Ordner::count());
    }

    public function test_pdf_und_bilder_oeffnen_in_der_vorschau(): void
    {
        Storage::fake('local');
        $m = $this->person('mitglied');
        $this->actingAs($m)->post(route('dateien.speichern'), ['dateien' => [
            UploadedFile::fake()->create('Reglement.pdf', 20, 'application/pdf'),
            UploadedFile::fake()->image('Plan.png'),
            UploadedFile::fake()->create('Budget.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ]]);
        $pdf = Dokument::where('titel', 'Reglement')->first();
        $this->assertSame('pdf', $pdf->vorschauTyp());
        $this->assertSame('bild', Dokument::where('titel', 'Plan')->first()->vorschauTyp());
        $this->assertNull(Dokument::where('titel', 'Budget')->first()->vorschauTyp());

        $seite = $this->actingAs($m)->get(route('dateien.index', ['ressort' => 'allgemein']))->assertOk();
        $seite->assertSee('$store.vorschau.oeffne', false)->assertSee('Vorschau schliessen');
        $antwort = $this->actingAs($m)->get(route('dokumente.download', [$pdf, 'ansehen' => 1]))->assertOk();
        $this->assertStringStartsWith('inline', (string) $antwort->headers->get('content-disposition'));
        $this->assertStringContainsString('pdf', (string) $antwort->headers->get('content-type'));
    }

    public function test_jahresplan_gilt_fuer_mehrere_turnierarten(): void
    {
        $dez = Turnier::where('kurz', 'DEZ')->first();
        $feb = Turnier::where('kurz', 'FEB')->first();
        $drei = Turnier::create(['name' => 'Hallencup Bottighofen', 'kurz' => 'BOT']);
        $r = Ressort::first();
        $admin = $this->person('admin');

        $this->actingAs($admin)->get(route('vorlagen.create'))->assertOk()->assertSee('Hallencup Bottighofen')->assertSee('turniere_gesendet', false);

        $daten = ['ressort_id' => $r->id, 'tage_vorher' => 30, 'prioritaet' => 'normal', 'turniere_gesendet' => 1];
        $this->actingAs($admin)->post(route('vorlagen.store'), $daten + ['titel' => 'Nur zwei QQQ', 'turniere' => [$dez->id, $drei->id]])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('vorlagen.store'), $daten + ['titel' => 'Alle QQQ', 'turniere' => [$dez->id, $feb->id, $drei->id]])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('vorlagen.store'), $daten + ['titel' => 'Keine QQQ'])->assertSessionHasErrors('turniere');

        $zwei = Vorlage::where('titel', 'Nur zwei QQQ')->first();
        $this->assertEqualsCanonicalizing([$dez->id, $drei->id], $zwei->turniere->pluck('id')->all());
        $this->assertSame('DEZ, BOT', $zwei->turnierartText());
        $this->assertCount(0, Vorlage::where('titel', 'Alle QQQ')->first()->turniere, 'Alle angehakt wird als "alle" gespeichert');

        $planer = app(AusgabePlaner::class);
        $titel = fn (Turnier $t) => $planer->vorschlag($t, Carbon::parse('2027-12-04'))->map(fn ($e) => $e['vorlage']->titel);
        $this->assertContains('Nur zwei QQQ', $titel($dez));
        $this->assertContains('Nur zwei QQQ', $titel($drei));
        $this->assertNotContains('Nur zwei QQQ', $titel($feb));
        $this->assertContains('Alle QQQ', $titel($feb));

        // Bearbeiten: nur noch Februar, mit Verlauf
        $this->actingAs($admin)->put(route('vorlagen.update', $zwei), $daten + ['titel' => 'Nur zwei QQQ', 'turniere' => [$feb->id]])->assertSessionHasNoErrors();
        $this->assertSame([$feb->id], $zwei->fresh()->turniere->pluck('id')->all());
        $this->actingAs($admin)->get(route('vorlagen.show', $zwei))->assertSee('Bei jedem Turnier (nur ');
        $this->actingAs($admin)->get(route('vorlagen.index', ['turnier' => $dez->id]))->assertDontSee('Nur zwei QQQ')->assertSee('Alle QQQ');

        // Export und Import nehmen die Turnierarten mit
        $export = $this->actingAs($admin)->get(route('admin.daten.vorlagen'))->streamedContent();
        $this->assertStringContainsString('"turniere"', $export);
        $paket = json_decode($export, true);
        $eintrag = collect($paket['vorlagen'])->firstWhere('titel', 'Nur zwei QQQ');
        $this->assertSame(['FEB'], $eintrag['turniere']);
    }

    public function test_migration_uebernimmt_bisherige_turnierart(): void
    {
        $dez = Turnier::where('kurz', 'DEZ')->first();
        $v = Vorlage::create(['ressort_id' => Ressort::first()->id, 'titel' => 'Alt QQQ', 'tage_vorher' => 10]);
        DB::table('vorlagen')->where('id', $v->id)->update(['turnier_id' => $dez->id]);
        DB::table('turnier_vorlage')->where('vorlage_id', $v->id)->delete();
        $migration = require database_path('migrations/2026_11_20_000700_ordner_und_turnierarten.php');
        $migration->down();
        $migration->up();
        $this->assertSame([$dez->id], $v->fresh()->turniere->pluck('id')->all());
    }
}
