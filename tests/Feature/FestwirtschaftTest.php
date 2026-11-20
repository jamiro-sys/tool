<?php

namespace Tests\Feature;

use App\Models\Ausgabe;
use App\Models\Auswahlliste;
use App\Models\EinkaufPosten;
use App\Models\Kasse;
use App\Models\Produkt;
use App\Models\Turnier;

class FestwirtschaftTest extends OkTestCase
{
    private function ausgabe(int $tage): Ausgabe
    {
        return Ausgabe::create(['turnier_id' => Turnier::first()->id, 'saison' => 'S'.$tage, 'titel' => 'Ausgabe '.$tage, 'datum_von' => today()->addDays($tage), 'status' => 'planung']);
    }

    public function test_produkte_mit_eigenen_allergenen_und_preisliste(): void
    {
        Auswahlliste::create(['bereich' => 'allergen', 'wert' => 'Gluten']);
        $m = $this->person('mitglied');
        $this->actingAs($m)->get(route('festwirtschaft.index'))->assertOk()->assertSee('Noch keine Produkte');
        $this->actingAs($m)->post(route('festwirtschaft.produkt'), ['name' => 'Bratwurst', 'preis' => '6.50', 'allergene' => ['Gluten']])->assertSessionHasNoErrors();
        $this->assertSame(['Gluten'], Produkt::first()->allergene);
        $this->actingAs($m)->get(route('festwirtschaft.preisliste'))->assertOk()->assertSee('Bratwurst')->assertSee('6.50')->assertSee('Gluten');
        $this->actingAs($this->person('lesend'))->post(route('festwirtschaft.produkt'), ['name' => 'X'])->assertForbidden();
    }

    public function test_einkauf_aus_letzter_ausgabe_mit_verbrauch_als_vorschlag(): void
    {
        $alt = $this->ausgabe(-180);
        $neu = $this->ausgabe(30);
        EinkaufPosten::create(['ausgabe_id' => $alt->id, 'bezeichnung' => 'Brötli', 'einheit' => 'Stück', 'bestellt' => 300, 'verbraucht' => 240]);
        $m = $this->person('mitglied');

        $this->actingAs($m)->get(route('festwirtschaft.ausgabe', $neu))->assertOk()->assertSee('übernehmen');
        $this->actingAs($m)->post(route('festwirtschaft.uebernehmen', $neu))->assertSessionHasNoErrors();
        $this->actingAs($m)->post(route('festwirtschaft.uebernehmen', $neu));
        $this->assertSame(1, $neu->einkauf()->count());
        $this->assertSame(240.0, $neu->einkauf()->first()->bestellt);

        $this->actingAs($m)->post(route('festwirtschaft.posten', $neu), ['bezeichnung' => ''])->assertSessionHasErrors('bezeichnung');
    }

    public function test_kasse_abschluss_braucht_zwei_personen(): void
    {
        $a = $this->ausgabe(5);
        $m = $this->person('mitglied');
        $this->actingAs($m)->post(route('festwirtschaft.kasse', $a), ['name' => 'Grill', 'start_bar' => 200, 'ende_bar' => 1200, 'ende_twint' => 300.5]);
        $k = Kasse::first();
        $this->assertSame(1300.5, $k->einnahmen());

        $this->actingAs($m)->post(route('festwirtschaft.kasse', [$a, $k]), ['name' => 'Grill', 'abschliessen' => 1, 'gezaehlt_von' => 'Lena'])->assertSessionHasErrors('zweite_person');
        $this->assertNull($k->fresh()->abgeschlossen_am);
        $this->actingAs($m)->post(route('festwirtschaft.kasse', [$a, $k]), ['name' => 'Grill', 'start_bar' => 200, 'ende_bar' => 1200, 'abschliessen' => 1, 'gezaehlt_von' => 'Lena', 'zweite_person' => 'Luca']);
        $this->assertNotNull($k->fresh()->abgeschlossen_am);

        $fremd = $this->ausgabe(60);
        $this->actingAs($m)->post(route('festwirtschaft.kasse', [$fremd, $k]), ['name' => 'X'])->assertNotFound();
    }
}
