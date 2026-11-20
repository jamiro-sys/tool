<?php

namespace Tests\Feature;

use App\Models\Ausgabe;
use App\Models\Auswahlliste;
use App\Models\Kennzahl;
use App\Models\Ressort;
use App\Models\Turnier;

class VerknuepfungTest extends OkTestCase
{
    private function ausgabe(): Ausgabe
    {
        return Ausgabe::create(['turnier_id' => Turnier::first()->id, 'saison' => '2026/27', 'titel' => 'Testausgabe', 'datum_von' => today()->addDays(30), 'status' => 'planung']);
    }

    public function test_listen_sind_ab_werk_leer_und_admin_pflegt_sie(): void
    {
        $this->assertSame(0, Auswahlliste::count());
        $admin = $this->person('admin');
        $this->actingAs($admin)->post(route('admin.auswahllisten.speichern'), ['bereich' => 'kennzahl', 'wert' => 'Teams'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.auswahllisten.speichern'), ['bereich' => 'kennzahl', 'wert' => 'Teams'])->assertSessionHasErrors('wert');
        $this->actingAs($this->person('mitglied'))->post(route('admin.auswahllisten.speichern'), ['bereich' => 'kennzahl', 'wert' => 'X'])->assertForbidden();
        $this->assertSame(['Teams'], Auswahlliste::werte('kennzahl')->all());
        $this->actingAs($admin)->get(route('admin.einstellungen'))->assertOk()->assertSee('Eigene Listen');
    }

    public function test_links_an_ausgabe_ressort_und_aufgabe(): void
    {
        $a = $this->ausgabe();
        $mitglied = $this->person('mitglied');
        $this->actingAs($mitglied)->post(route('verknuepfungen.speichern'), ['bezug_typ' => 'ausgabe', 'bezug_id' => $a->id, 'titel' => 'Spielplan', 'url' => 'https://www.turnieragenda.ch/x', 'werkzeug' => 'turnieragenda'])->assertSessionHasNoErrors();
        $this->actingAs($mitglied)->post(route('verknuepfungen.speichern'), ['bezug_typ' => 'ressort', 'bezug_id' => Ressort::first()->id, 'titel' => 'Schichten', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->actingAs($this->person('lesend'))->post(route('verknuepfungen.speichern'), ['bezug_typ' => 'ausgabe', 'bezug_id' => $a->id, 'titel' => 'X', 'url' => 'https://example.ch'])->assertForbidden();

        $this->actingAs($mitglied)->get(route('ausgaben.show', $a))->assertOk()->assertSee('Spielplan')->assertSee('turnieragenda.ch');
        $this->assertSame(1, $a->verknuepfungen()->count());
    }

    public function test_kennzahlen_nur_aus_eigener_liste(): void
    {
        $a = $this->ausgabe();
        Auswahlliste::create(['bereich' => 'kennzahl', 'wert' => 'Teams']);
        $mitglied = $this->person('mitglied');
        $this->actingAs($mitglied)->post(route('ausgaben.kennzahlen', $a), ['werte' => ['Teams' => '24', 'Fremd' => '5']])->assertSessionHasNoErrors();
        $this->assertSame(24.0, Kennzahl::where('name', 'Teams')->value('wert') + 0.0);
        $this->assertFalse(Kennzahl::where('name', 'Fremd')->exists());

        $this->actingAs($mitglied)->post(route('ausgaben.kennzahlen', $a), ['werte' => ['Teams' => '']]);
        $this->assertSame(0, Kennzahl::count());

        $admin = $this->person('admin');
        $admin->update(['ansicht' => 'experte']);
        Kennzahl::create(['ausgabe_id' => $a->id, 'name' => 'Teams', 'wert' => 20]);
        $this->actingAs($admin->fresh())->get(route('auswertung'))->assertOk()->assertSee('Kennzahlen im Vergleich');
    }
}
