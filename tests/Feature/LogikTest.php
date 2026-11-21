<?php

namespace Tests\Feature;

use App\Models\Aufgabe;
use App\Models\Ausgabe;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\Uebergabe;
use App\Models\Vorlage;
use App\Services\Sicherung;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/** Rechenlogik und Datenpflege nach der Gesamtpruefung. */
class LogikTest extends OkTestCase
{
    protected function turnierAm(string $titel, int $inTagen, string $status = 'planung', ?int $dauer = 1): Ausgabe
    {
        $von = today()->addDays($inTagen);

        return Ausgabe::create(['turnier_id' => Turnier::first()->id, 'saison' => '2026/27', 'titel' => $titel, 'datum_von' => $von, 'datum_bis' => $dauer ? $von->copy()->addDays($dauer) : null, 'status' => $status]);
    }

    public function test_aktuelles_turnier_ist_das_naechste_nicht_das_aelteste(): void
    {
        $this->turnierAm('Vorbei QQ', -60, 'rueckblick');
        $kommend = $this->turnierAm('Kommend QQ', 30);
        $this->turnierAm('Spaeter QQ', 120);
        $this->assertSame($kommend->id, Ausgabe::naechste()->id);

        // Nur vergangene offen: das zuletzt vergangene
        Ausgabe::whereIn('titel', ['Kommend QQ', 'Spaeter QQ'])->delete();
        $juenger = $this->turnierAm('Juenger vorbei QQ', -10, 'rueckblick');
        $this->assertSame($juenger->id, Ausgabe::naechste()->id);
    }

    public function test_keine_erinnerungen_fuer_lange_vergangene_turniere(): void
    {
        Notification::fake();
        $r = Ressort::first();
        $m = $this->person('mitglied', $r->kuerzel);
        $alt = $this->turnierAm('Alt QQ', -90, 'rueckblick');
        $neu = $this->turnierAm('Neu QQ', -3, 'rueckblick');
        foreach ([$alt, $neu] as $a) {
            Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'titel' => 'Offen '.$a->titel, 'status' => 'offen', 'verantwortlich_id' => $m->id, 'faellig_am' => today()->subDays(1)]);
        }
        $this->artisan('ok:erinnerungen')->assertSuccessful();
        $this->assertNotNull(Aufgabe::where('titel', 'Offen Neu QQ')->value('erinnert_am'));
        $this->assertNull(Aufgabe::where('titel', 'Offen Alt QQ')->value('erinnert_am'));
    }

    public function test_faelligkeit_als_satz_fuer_meldungen(): void
    {
        $a = $this->turnierAm('Satz QQ', 40);
        $r = Ressort::first();
        $neu = fn (array $w) => Aufgabe::create($w + ['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'titel' => 'X', 'status' => 'offen']);
        $this->assertSame('Fällig heute', $neu(['faellig_am' => today()])->faelligSatz());
        $this->assertSame('Fällig in 4 Tagen', $neu(['faellig_am' => today()->addDays(4)])->faelligSatz());
        $this->assertStringStartsWith('Fällig am ', $neu(['faellig_am' => today()->addDays(40)])->faelligSatz());
        $this->assertSame('Ohne Termin', $neu([])->faelligSatz());
    }

    public function test_turnierende_nicht_vor_beginn(): void
    {
        $a = $this->turnierAm('Datum QQ', 40);
        $admin = $this->person('admin');
        $this->actingAs($admin)->patch(route('ausgaben.update', $a), ['datum_bis' => $a->datum_von->copy()->subDay()->format('Y-m-d')])->assertSessionHasErrors('datum_bis');
        $this->actingAs($admin)->patch(route('ausgaben.update', $a), ['datum_von' => $a->datum_bis->copy()->addDays(3)->format('Y-m-d')])->assertSessionHasErrors('datum_bis');
    }

    public function test_aeltere_sicherung_wird_vollstaendig_und_umgerechnet_eingespielt(): void
    {
        Storage::fake('local');
        $s = app(Sicherung::class);
        $a = $this->turnierAm('Sicherung QQ', 40);
        $r = Ressort::first();
        Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'titel' => 'Alt QQ', 'status' => 'offen', 'faellig_am' => $a->datum_von->copy()->subDays(10)]);
        $v = Vorlage::create(['ressort_id' => $r->id, 'titel' => 'Nur Dezember QQ', 'tage_vorher' => 7, 'aktiv' => true]);
        DB::table('vorlagen')->where('id', $v->id)->update(['turnier_id' => Turnier::first()->id]);
        $name = $s->erstelle('test');

        // Sicherung so umbauen, wie sie vor den neuen Spalten und der Mehrfachauswahl aussah
        $pfad = Sicherung::ORDNER.'/'.$name;
        $inhalt = json_decode(gzdecode(Storage::disk('local')->get($pfad)), true);
        unset($inhalt['tabellen']['turnier_vorlage'], $inhalt['tabellen']['ordner']);
        $inhalt['tabellen']['aufgaben'] = array_map(fn ($z) => array_diff_key($z, ['tage_vorher' => 1, 'bezug' => 1]), $inhalt['tabellen']['aufgaben']);
        Storage::disk('local')->put($pfad, gzencode(json_encode($inhalt)));

        // Inzwischen dazugekommene Daten muessen beim Einspielen verschwinden
        \App\Models\Ordner::create(['ressort_id' => $r->id, 'name' => 'Neu seither QQ']);
        $s->wiederherstellen($name);

        $this->assertSame(0, \App\Models\Ordner::count());
        $this->assertSame(10, Aufgabe::where('titel', 'Alt QQ')->value('tage_vorher'));
        $this->assertSame([Turnier::first()->id], Vorlage::where('titel', 'Nur Dezember QQ')->first()->turniere->pluck('id')->all());
    }

    public function test_datenexport_ist_keine_sicherung_und_aufraeumen_behaelt_schutzkopien(): void
    {
        Storage::fake('local');
        $s = app(Sicherung::class);
        Storage::disk('local')->put(Sicherung::ORDNER.'/sicherung-2020-01-01-000000-export.json.gz', gzencode(json_encode(['tabellen' => ['users' => [['id' => 1, 'name' => 'X', 'email' => 'x@example.ch']]]])));
        try {
            $s->wiederherstellen('sicherung-2020-01-01-000000-export.json.gz');
            $this->fail('Export haette abgelehnt werden muessen');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        foreach (['automatisch', 'vor-leeren', 'manuell'] as $anlass) {
            Storage::disk('local')->put(Sicherung::ORDNER."/sicherung-2020-01-01-000000-{$anlass}.json.gz", 'x');
            touch(Storage::disk('local')->path(Sicherung::ORDNER."/sicherung-2020-01-01-000000-{$anlass}.json.gz"), now()->subDays(60)->getTimestamp());
        }
        $s->aufraeumen(14);
        $namen = collect($s->liste())->pluck('name');
        $this->assertNotContains('sicherung-2020-01-01-000000-automatisch.json.gz', $namen);
        $this->assertContains('sicherung-2020-01-01-000000-vor-leeren.json.gz', $namen);
        $this->assertContains('sicherung-2020-01-01-000000-manuell.json.gz', $namen);
    }

    public function test_abgebrochene_uebergabe_nimmt_einblick_wieder_weg_und_rolle_behaelt_seit(): void
    {
        $r = Ressort::first();
        $leitung = $this->person('mitglied', $r->kuerzel, 'leitung');
        $neu = $this->person('mitglied');
        $this->actingAs($leitung)->post(route('uebergaben.store'), ['ressort_id' => $r->id, 'an_id' => $neu->id])->assertSessionHasNoErrors();
        $this->assertTrue($neu->fresh()->gehoertZuRessort($r));
        $u = Uebergabe::latest('id')->firstOrFail();
        $this->actingAs($leitung)->post(route('uebergaben.abbrechen', $u));
        $this->assertFalse($neu->fresh()->gehoertZuRessort($r));

        // Rolle wechseln behaelt das Eintrittsdatum
        $m = $this->person('mitglied', $r->kuerzel);
        DB::table('ressort_user')->where('user_id', $m->id)->update(['seit' => '2020-05-01 00:00:00']);
        $this->actingAs($leitung)->post(route('ressorts.mitglied', $r), ['user_id' => $m->id, 'rolle' => 'nachfolge']);
        $this->assertStringStartsWith('2020-05-01', (string) DB::table('ressort_user')->where('user_id', $m->id)->value('seit'));
    }
}
