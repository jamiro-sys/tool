<?php

namespace Tests\Feature;

use App\Models\Aufgabe;
use App\Models\Ausgabe;
use App\Models\Ressort;
use App\Models\Turnier;

class AufgabenEinfachTest extends OkTestCase
{
    protected function turnier(): Ausgabe
    {
        $t = Turnier::first() ?? Turnier::create(['name' => 'Hallencup Dezember', 'kurz' => 'Dezember']);

        return Ausgabe::create(['turnier_id' => $t->id, 'saison' => '2026/27', 'titel' => 'Hallencup QQ', 'datum_von' => today()->addDays(40), 'status' => 'planung']);
    }

    public function test_faelligkeit_einheitlich(): void
    {
        $a = $this->turnier();
        $r = Ressort::first();
        $neu = fn (array $w) => Aufgabe::create($w + ['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'titel' => 'X', 'status' => 'offen']);

        $this->assertSame('ohne Termin', $neu([])->faelligText());
        $this->assertSame('heute', $neu(['faellig_am' => today()])->faelligText());
        $this->assertSame('seit 3 Tagen', $neu(['faellig_am' => today()->subDays(3)])->faelligText());
        $this->assertSame('in 4 Tagen', $neu(['faellig_am' => today()->addDays(4)])->faelligText());
        $weit = today()->addDays(30);
        $erwartet = $weit->copy()->locale('de_CH')->isoFormat($weit->year === today()->year ? 'dd, D. MMM' : 'dd, D. MMM YYYY');
        $this->assertSame($erwartet, $neu(['faellig_am' => $weit])->faelligText());
        // Erledigte zeigen nur das Datum, nie "seit ..."
        $this->assertStringNotContainsString('seit', $neu(['faellig_am' => today()->subDays(3), 'status' => 'erledigt'])->faelligText());
    }

    public function test_erstellen_mit_checkliste_und_wichtig(): void
    {
        $a = $this->turnier();
        $r = Ressort::first();
        $m = $this->person('mitglied', $r->kuerzel);
        $this->actingAs($m)->get(route('aufgaben.index', $a))->assertOk()->assertSee('Weitere Angaben')->assertSee('Wer macht es?');

        $this->actingAs($m)->post(route('aufgaben.store', $a), [
            'titel' => 'Pokale QQ', 'ressort_id' => $r->id, 'prioritaet' => 'hoch',
            'checkliste_text' => "Offerte einholen\n\nBestellen ", 'wann_gesendet' => 1, 'tage_vorher' => 14, 'bezug' => 'beginn',
        ])->assertRedirect();
        $aufgabe = Aufgabe::where('titel', 'Pokale QQ')->firstOrFail();
        $this->assertSame('hoch', $aufgabe->prioritaet);
        $this->assertSame([['text' => 'Offerte einholen', 'erledigt' => false], ['text' => 'Bestellen', 'erledigt' => false]], $aufgabe->checkliste);

        // Ohne Haekchen "Wichtig" bleibt normal
        $this->actingAs($m)->post(route('aufgaben.store', $a), ['titel' => 'Normal QQ', 'ressort_id' => $r->id, 'prioritaet' => 'normal'])->assertRedirect();
        $this->assertSame('normal', Aufgabe::where('titel', 'Normal QQ')->value('prioritaet'));
    }

    public function test_detail_zeigt_steckbrief_zuerst_und_einen_erledigt_knopf(): void
    {
        $a = $this->turnier();
        $r = Ressort::first();
        $m = $this->person('mitglied', $r->kuerzel);
        $aufgabe = Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'titel' => 'Detail QQ', 'status' => 'offen', 'verantwortlich_id' => $m->id,
            'faellig_am' => today()->addDays(10), 'checkliste' => [['text' => 'Punkt QQ', 'erledigt' => false]]]);

        $this->actingAs($m)->get(route('aufgaben.show', $aufgabe))->assertOk()
            ->assertSeeInOrder(['data-steckbrief', 'Fällig', 'Wer', $m->name, 'Als erledigt markieren', 'Checkliste', 'Punkt QQ', 'Absprachen'])
            ->assertDontSee('Bei welchen Turnieren?');

        // Abhaken mit Aufwand in einem Schritt
        $this->actingAs($m)->post(route('aufgaben.status', $aufgabe), ['status' => 'erledigt', 'aufwand_effektiv' => 1.5])->assertRedirect();
        $aufgabe->refresh();
        $this->assertSame('erledigt', $aufgabe->status);
        $this->assertEquals(1.5, $aufgabe->aufwand_effektiv);
    }

    public function test_liste_erledigte_am_ende_der_gruppe(): void
    {
        $a = $this->turnier();
        $r = Ressort::first();
        $admin = $this->person('admin');
        Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'titel' => 'Fertig QQ', 'status' => 'erledigt', 'faellig_am' => today()->subDays(20)]);
        Aufgabe::create(['ausgabe_id' => $a->id, 'ressort_id' => $r->id, 'titel' => 'Offen QQ', 'status' => 'offen', 'faellig_am' => today()->addDays(20)]);

        $this->actingAs($admin)->get(route('aufgaben.index', $a))->assertOk()
            ->assertSeeInOrder(['Offen QQ', '1 erledigt anzeigen', 'Fertig QQ']);
    }
}
