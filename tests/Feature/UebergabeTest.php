<?php

namespace Tests\Feature;

use App\Models\Ressort;
use App\Models\Uebergabe;

class UebergabeTest extends OkTestCase
{
    public function test_leitung_wechselt_erst_nach_checkliste_und_beiden_bestaetigungen(): void
    {
        $alt = $this->person('mitglied', 'SPO', 'leitung');
        $neu = $this->person();
        $ressort = Ressort::where('kuerzel', 'SPO')->first();

        $this->actingAs($alt)->post(route('uebergaben.store'), ['ressort_id' => $ressort->id, 'an_id' => $neu->id])->assertRedirect();
        $ue = Uebergabe::firstOrFail();
        $this->assertSame('nachfolge', $ressort->mitglieder()->find($neu->id)->pivot->rolle);

        // Bestaetigen ohne erledigte Checkliste geht nicht
        $this->actingAs($alt)->post(route('uebergaben.bestaetigen', $ue))->assertStatus(422);

        foreach (array_keys($ue->checkliste) as $i) {
            $this->actingAs($alt)->postJson(route('uebergaben.checkpunkt', $ue), ['index' => $i])->assertOk();
        }
        $this->actingAs($alt)->post(route('uebergaben.bestaetigen', $ue))->assertRedirect();
        $this->assertSame('in_arbeit', $ue->fresh()->status);
        $this->assertSame('leitung', $ressort->mitglieder()->find($alt->id)->pivot->rolle);

        $this->actingAs($neu)->post(route('uebergaben.bestaetigen', $ue))->assertRedirect();
        $this->assertSame('abgeschlossen', $ue->fresh()->status);
        $this->assertSame('leitung', $ressort->mitglieder()->find($neu->id)->pivot->rolle);
        $this->assertSame('mitglied', $ressort->mitglieder()->find($alt->id)->pivot->rolle);
    }

    public function test_aussenstehende_koennen_nicht_mitwirken(): void
    {
        $alt = $this->person('mitglied', 'SPO', 'leitung');
        $ressort = Ressort::where('kuerzel', 'SPO')->first();
        $this->actingAs($alt)->post(route('uebergaben.store'), ['ressort_id' => $ressort->id, 'an_id' => $this->person()->id]);
        $this->actingAs($this->person())->postJson(route('uebergaben.checkpunkt', Uebergabe::first()), ['index' => 0])->assertForbidden();
    }

    public function test_wissensstand_steigt_mit_inhalten(): void
    {
        $ressort = Ressort::with('mitglieder', 'vorlagen', 'kontakte', 'wissen')->where('kuerzel', 'FW')->first();
        $vorher = $ressort->wissensstand()['punkte'];
        $ressort->kontakte()->create(['name' => 'Lieferant']);
        $ressort->wissen()->create(['titel' => 'Ablauf', 'inhalt' => 'So geht es']);
        $nachher = $ressort->fresh(['mitglieder', 'vorlagen', 'kontakte', 'wissen'])->wissensstand()['punkte'];
        $this->assertGreaterThan($vorher, $nachher);
    }
}
