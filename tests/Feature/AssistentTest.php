<?php

namespace Tests\Feature;

use App\Models\Aufgabe;
use App\Models\Ausgabe;
use App\Models\Einstellung;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\Vorlage;
use App\Services\AufgabenAssistent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;

class AssistentTest extends OkTestCase
{
    /** Ersetzt den Aufruf an Claude durch eine feste Antwort und merkt sich, was gesendet wurde. */
    private function fake(array $antwort, ?\Throwable $fehler = null): object
    {
        $fake = new class($antwort, $fehler) extends AufgabenAssistent
        {
            public array $gesendet = [];

            public string $system = '';

            public function __construct(private array $antwort, private ?\Throwable $fehler) {}

            public function schluessel(): ?string
            {
                return 'sk-ant-test';
            }

            protected function frage(string $system, array $inhalt): array
            {
                $this->system = $system;
                $this->gesendet = $inhalt;
                if ($this->fehler) {
                    throw $this->fehler;
                }

                return $this->antwort;
            }
        };
        $this->app->instance(AufgabenAssistent::class, $fake);

        return $fake;
    }

    private function aufgabe(array $werte): array
    {
        return $werte + ['beschreibung' => null, 'ressort' => null, 'wann' => 'ohne', 'abstand_tage' => null, 'aufwand_minuten' => null, 'prioritaet' => 'normal', 'jedes_jahr' => true, 'turnierarten' => [], 'checkliste' => []];
    }

    public function test_schluessel_nur_admin_und_verschluesselt(): void
    {
        $this->actingAs($this->person('mitglied'))->post(route('assistent.schluessel'), ['schluessel' => 'sk-ant-abc'])->assertForbidden();
        $admin = $this->person('admin');
        $this->actingAs($admin)->get(route('admin.system'))->assertSee('Claude-Assistent')->assertSee('nicht eingerichtet');
        $this->actingAs($admin)->post(route('assistent.schluessel'), ['schluessel' => 'falsch'])->assertSessionHasErrors('schluessel');
        $this->actingAs($admin)->post(route('assistent.schluessel'), ['schluessel' => 'sk-ant-geheim123'])->assertSessionHasNoErrors();

        $gespeichert = Einstellung::wert('claude_schluessel');
        $this->assertStringNotContainsString('geheim', (string) $gespeichert);
        $this->assertSame('sk-ant-geheim123', Crypt::decryptString($gespeichert));
        $this->assertTrue(app(AufgabenAssistent::class)->bereit());
        $this->actingAs($admin)->get(route('admin.system'))->assertSee('bereit')->assertDontSee('sk-ant-geheim123');

        $this->actingAs($admin)->post(route('assistent.schluessel'), ['entfernen' => 1]);
        $this->assertFalse(app(AufgabenAssistent::class)->bereit());
    }

    public function test_ohne_schluessel_hinweis_statt_formular(): void
    {
        $admin = $this->person('admin');
        $this->actingAs($admin)->get(route('vorlagen.index'))->assertSee('Mit Claude erfassen')->assertSee('noch nicht eingerichtet');
        $this->actingAs($admin)->post(route('assistent.vorschlag'), ['art' => 'jahresplan', 'text' => 'x'])->assertStatus(503);
    }

    public function test_jahresplan_vorschlaege_pruefen_und_uebernehmen(): void
    {
        $admin = $this->person('admin');
        $r = Ressort::where('kuerzel', 'INF')->first();
        $feb = Turnier::where('kurz', 'FEB')->first();
        $fake = $this->fake(['hinweis' => 'Bei «Flyer» war kein Zeitpunkt genannt.', 'aufgaben' => [
            $this->aufgabe(['titel' => 'Halle reservieren QQ', 'ressort' => 'Infrastruktur', 'wann' => 'vor', 'abstand_tage' => 300, 'aufwand_minuten' => 90, 'prioritaet' => 'hoch']),
            $this->aufgabe(['titel' => 'Abrechnung QQ', 'ressort' => 'FIN', 'wann' => 'nach', 'abstand_tage' => 14, 'turnierarten' => ['Hallencup Februar'], 'checkliste' => ['Belege sammeln']]),
            $this->aufgabe(['titel' => 'Flyer QQ', 'ressort' => 'Gibt es nicht']),
        ]]);

        $seite = $this->actingAs($admin)->post(route('assistent.vorschlag'), ['art' => 'jahresplan', 'text' => 'Halle 10 Monate vorher ...'])->assertOk();
        $seite->assertSee('Vorschläge prüfen')->assertSee('Halle reservieren QQ')->assertSee('Bei «Flyer» war kein Zeitpunkt genannt.')->assertSee('Ressort bitte wählen');
        $this->assertStringContainsString('Infrastruktur (Kürzel INF)', $fake->system);
        $this->assertStringContainsString('Hallencup Februar', $fake->system);

        $this->actingAs($admin)->post(route('assistent.uebernehmen'), ['art' => 'jahresplan', 'zeilen' => [
            ['nehmen' => 1, 'titel' => 'Halle reservieren QQ', 'ressort_id' => $r->id, 'tage_vorher' => 300, 'bezug' => 'beginn', 'aufwand' => 1.5, 'prioritaet' => 'hoch'],
            ['nehmen' => 1, 'titel' => 'Abrechnung QQ', 'ressort_id' => Ressort::where('kuerzel', 'FIN')->value('id'), 'tage_vorher' => -14, 'bezug' => 'ende', 'turniere' => [$feb->id], 'checkliste' => "Belege sammeln\n"],
            ['nehmen' => 0, 'titel' => 'Flyer QQ', 'ressort_id' => $r->id],
        ]])->assertRedirect(route('vorlagen.index'))->assertSessionHas('erfolg', '2 Aufgaben sind im Jahresplan.');

        $halle = Vorlage::where('titel', 'Halle reservieren QQ')->first();
        $this->assertSame([300, 'beginn', 1.5, 'hoch', '10 Monate vorher'], [$halle->tage_vorher, $halle->bezug, $halle->aufwand_std, $halle->prioritaet, $halle->zeitpunktText()]);
        $abrechnung = Vorlage::where('titel', 'Abrechnung QQ')->first();
        $this->assertSame([-14, 'ende', ['Belege sammeln'], [$feb->id]], [$abrechnung->tage_vorher, $abrechnung->bezug, $abrechnung->checkliste, $abrechnung->turniere->pluck('id')->all()]);
        $this->assertNull(Vorlage::where('titel', 'Flyer QQ')->first());
    }

    public function test_ressort_aufgaben_mit_dateien_und_jedes_jahr(): void
    {
        $a = Ausgabe::create(['turnier_id' => Turnier::first()->id, 'saison' => '2026/27', 'titel' => 'Cup QQ', 'datum_von' => today()->addDays(40), 'datum_bis' => today()->addDays(41), 'status' => 'planung']);
        $r = Ressort::where('kuerzel', 'FIN')->first();
        $leitung = $this->person('mitglied', 'FIN', 'leitung');
        $fake = $this->fake(['hinweis' => null, 'aufgaben' => [
            $this->aufgabe(['titel' => 'Stockgeld QQ', 'wann' => 'vor', 'abstand_tage' => 7, 'jedes_jahr' => true]),
            $this->aufgabe(['titel' => 'Sonderkasse QQ', 'wann' => 'letzter', 'jedes_jahr' => false]),
        ]]);

        $this->actingAs($leitung)->get(route('ressorts.show', [$r, 'tab' => 'aufgaben']))->assertSee('modal-assistent', false);
        $this->actingAs($leitung)->post(route('assistent.vorschlag'), ['art' => 'ressort', 'ressort_id' => $r->id, 'dateien' => [
            UploadedFile::fake()->create('liste.pdf', 20, 'application/pdf'), UploadedFile::fake()->image('notiz.png'),
        ]])->assertOk()->assertSee('Stockgeld QQ')->assertSee('Jedes Jahr (in den Jahresplan)');
        $this->assertSame(['document', 'image', 'text'], array_column($fake->gesendet, 'type'));
        $this->assertSame('application/pdf', $fake->gesendet[0]['source']['mediaType']);

        $this->actingAs($leitung)->post(route('assistent.uebernehmen'), ['art' => 'ressort', 'ressort_id' => $r->id, 'zeilen' => [
            ['nehmen' => 1, 'titel' => 'Stockgeld QQ', 'tage_vorher' => 7, 'bezug' => 'beginn', 'jedes_jahr' => 1],
            ['nehmen' => 1, 'titel' => 'Sonderkasse QQ', 'tage_vorher' => 0, 'bezug' => 'ende', 'jedes_jahr' => 0],
        ]])->assertRedirect(route('ressorts.show', [$r, 'tab' => 'aufgaben']));

        $stock = Aufgabe::where('titel', 'Stockgeld QQ')->first();
        $this->assertSame([$a->id, $r->id, today()->addDays(33)->toDateString()], [$stock->ausgabe_id, $stock->ressort_id, $stock->faellig_am->toDateString()]);
        $this->assertNotNull($stock->vorlage_id);
        $kasse = Aufgabe::where('titel', 'Sonderkasse QQ')->first();
        $this->assertSame(today()->addDays(41)->toDateString(), $kasse->faellig_am->toDateString());
        $this->assertNull($kasse->vorlage_id);
    }

    public function test_rechte_und_fehler(): void
    {
        $this->fake(['hinweis' => null, 'aufgaben' => []]);
        $m = $this->person('mitglied', 'FIN');
        $this->actingAs($m)->post(route('assistent.vorschlag'), ['art' => 'jahresplan', 'text' => 'x'])->assertForbidden();
        $this->actingAs($m)->post(route('assistent.vorschlag'), ['art' => 'ressort', 'ressort_id' => Ressort::where('kuerzel', 'SPO')->value('id'), 'text' => 'x'])->assertForbidden();
        $this->actingAs($this->person('admin'))->post(route('assistent.vorschlag'), ['art' => 'jahresplan', 'text' => 'nichts'])
            ->assertRedirect(route('vorlagen.index'))->assertSessionHasErrors('assistent');

        $this->fake([], new \RuntimeException('Claude hat die Anfrage abgelehnt. Bitte den Text anders formulieren.'));
        $this->actingAs($this->person('admin'))->post(route('assistent.vorschlag'), ['art' => 'jahresplan', 'text' => 'x'])
            ->assertSessionHasErrors(['assistent' => 'Claude hat die Anfrage abgelehnt. Bitte den Text anders formulieren.']);
        $this->actingAs($this->person('admin'))->post(route('assistent.vorschlag'), ['art' => 'jahresplan', 'dateien' => [UploadedFile::fake()->create('a.docx', 5, 'application/msword')]])
            ->assertSessionHasErrors('dateien.0');
    }

    public function test_schema_ist_streng(): void
    {
        $s = AufgabenAssistent::schema();
        $this->assertFalse($s['additionalProperties']);
        $item = $s['properties']['aufgaben']['items'];
        $this->assertEqualsCanonicalizing(array_keys($item['properties']), $item['required']);
    }
}
