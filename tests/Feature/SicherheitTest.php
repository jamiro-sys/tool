<?php

namespace Tests\Feature;

use App\Models\Aufgabe;
use App\Models\Ausgabe;
use App\Models\Dokument;
use App\Models\Erkenntnis;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\User;
use App\Models\Vorlage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Rechte ueber Ressortgrenzen und sichere Dateianzeige. */
class SicherheitTest extends OkTestCase
{
    protected function turnier(): Ausgabe
    {
        return Ausgabe::create(['turnier_id' => Turnier::first()->id, 'saison' => '2026/27', 'titel' => 'Hallencup QQ', 'datum_von' => today()->addDays(40), 'status' => 'planung']);
    }

    public function test_svg_wird_nie_direkt_angezeigt_pdf_schon(): void
    {
        Storage::fake('local');
        $m = $this->person('mitglied');
        $this->actingAs($m)->post(route('dateien.speichern'), ['dateien' => [
            UploadedFile::fake()->createWithContent('bild.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf'),
        ]])->assertSessionHasNoErrors();

        $svg = Dokument::where('original_name', 'bild.svg')->firstOrFail();
        $antwort = $this->actingAs($m)->get(route('dokumente.download', [$svg, 'ansehen' => 1]));
        $this->assertStringContainsString('attachment', (string) $antwort->headers->get('content-disposition'));
        $this->assertSame('nosniff', $antwort->headers->get('x-content-type-options'));

        $pdf = Dokument::where('original_name', 'plan.pdf')->firstOrFail();
        $this->assertStringContainsString('inline', (string) $this->actingAs($m)->get(route('dokumente.download', [$pdf, 'ansehen' => 1]))->headers->get('content-disposition'));
    }

    public function test_aufgabe_nicht_in_fremdes_ressort_verschieben(): void
    {
        $a = $this->turnier();
        [$r1, $r2] = Ressort::orderBy('sortierung')->take(2)->get()->all();
        $m = $this->person('mitglied', $r1->kuerzel);
        $aufgabe = Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r1->id, 'titel' => 'Hier QQ', 'status' => 'offen']);

        $this->actingAs($m)->patch(route('aufgaben.update', $aufgabe), ['ressort_id' => $r2->id])->assertForbidden();
        $this->assertSame($r1->id, $aufgabe->fresh()->ressort_id);
        $this->actingAs($m)->patch(route('aufgaben.update', $aufgabe), ['ressort_id' => $r1->id, 'titel' => 'Hier QQ 2'])->assertSessionHasNoErrors();
        $this->assertSame('Hier QQ 2', $aufgabe->fresh()->titel);
    }

    public function test_jahresplan_eintrag_nicht_in_fremdes_ressort(): void
    {
        [$r1, $r2] = Ressort::orderBy('sortierung')->take(2)->get()->all();
        $leitung = $this->person('mitglied', $r1->kuerzel, 'leitung');
        $v = Vorlage::create(['ressort_id' => $r1->id, 'titel' => 'Plan QQ', 'tage_vorher' => 14, 'aktiv' => true]);

        $this->actingAs($leitung)->put(route('vorlagen.update', $v), ['ressort_id' => $r2->id, 'titel' => 'Plan QQ', 'tage_vorher' => 14, 'prioritaet' => 'normal'])->assertForbidden();
        $this->actingAs($leitung)->put(route('vorlagen.update', $v), ['ressort_id' => $r1->id, 'titel' => 'Plan QQ 2', 'tage_vorher' => 14, 'prioritaet' => 'normal'])->assertSessionHasNoErrors();
        $this->assertSame($r1->id, $v->fresh()->ressort_id);
    }

    public function test_erkenntnis_gehoert_zum_ressort_des_jahresplans_und_nur_einmal(): void
    {
        $a = $this->turnier();
        [$r1, $r2] = Ressort::orderBy('sortierung')->take(2)->get()->all();
        $leitung = $this->person('mitglied', $r1->kuerzel, 'leitung');
        $fremd = Vorlage::create(['ressort_id' => $r2->id, 'titel' => 'Fremd QQ', 'tage_vorher' => 14, 'aktiv' => true]);

        // Ressort wird vom Jahresplan-Eintrag uebernommen, dann darf die Leitung von r1 nicht uebernehmen
        $this->actingAs($leitung)->post(route('erkenntnisse.store'), ['text' => 'Weg damit', 'vorschlag' => 'streichen', 'ressort_id' => $r1->id, 'vorlage_id' => $fremd->id]);
        $e = Erkenntnis::latest('id')->firstOrFail();
        $this->assertSame($r2->id, $e->ressort_id);
        $this->actingAs($leitung)->post(route('erkenntnisse.uebernehmen', $e))->assertForbidden();
        $this->assertTrue($fremd->fresh()->aktiv);

        // "Neu" zweimal abschicken ergibt nur einen Eintrag
        $aufgabe = Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r1->id, 'titel' => 'Neu QQ', 'status' => 'erledigt']);
        $this->actingAs($leitung)->post(route('erkenntnisse.store'), ['text' => 'Jedes Jahr machen', 'vorschlag' => 'neu', 'aufgabe_id' => $aufgabe->id]);
        $e2 = Erkenntnis::latest('id')->firstOrFail();
        $this->actingAs($leitung)->post(route('erkenntnisse.uebernehmen', $e2));
        $this->actingAs($leitung)->post(route('erkenntnisse.uebernehmen', $e2));
        $this->assertSame(1, Vorlage::where('titel', 'Neu QQ')->count());
    }

    public function test_links_nur_wo_man_mitarbeitet(): void
    {
        [$r1, $r2] = Ressort::orderBy('sortierung')->take(2)->get()->all();
        $m = $this->person('mitglied', $r1->kuerzel);
        $this->actingAs($m)->post(route('verknuepfungen.speichern'), ['bezug_typ' => 'ressort', 'bezug_id' => $r2->id, 'titel' => 'X', 'url' => 'https://example.ch'])->assertForbidden();
        $this->actingAs($m)->post(route('verknuepfungen.speichern'), ['bezug_typ' => 'ressort', 'bezug_id' => $r1->id, 'titel' => 'X', 'url' => 'https://example.ch'])->assertSessionHasNoErrors();
    }

    public function test_push_nur_an_echte_dienste_und_keine_einladung_fuer_gesperrte(): void
    {
        $m = $this->person('mitglied');
        $abo = fn (string $url) => $this->actingAs($m)->postJson(route('push.abo'), ['endpoint' => $url, 'keys' => ['p256dh' => 'a', 'auth' => 'b']]);
        $abo('http://169.254.169.254/latest')->assertStatus(422);
        $abo('https://intern.example.ch/push')->assertStatus(422);
        $abo('https://fcm.googleapis.com/fcm/send/abc')->assertSuccessful();

        $gesperrt = User::create(['name' => 'Weg QQ', 'email' => 'weg@example.ch', 'rolle' => 'mitglied', 'aktiv' => false, 'einladung_token' => 'tok123']);
        auth()->logout();
        $this->get('/einladung/tok123')->assertNotFound();
    }
}
