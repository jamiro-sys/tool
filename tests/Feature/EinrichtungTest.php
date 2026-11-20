<?php

namespace Tests\Feature;

use App\Models\Ausgabe;
use App\Models\Halle;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\User;
use App\Models\Vorlage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Einrichtung von einem leeren Tool bis zur ersten Ausgabe, ohne Grunddaten. */
class EinrichtungTest extends TestCase
{
    use RefreshDatabase;

    public function test_von_leer_bis_zur_ersten_ausgabe(): void
    {
        $this->withoutVite();
        $admin = User::create(['name' => 'Admin', 'email' => 'a@example.ch', 'password' => 'passwort123', 'rolle' => 'admin', 'aktiv' => true]);
        $this->actingAs($admin);

        $this->get(route('start'))->assertOk()->assertSee('Erste Schritte')->assertSee('0 von 6');
        $this->get(route('ausgaben.create'))->assertOk()->assertSee('Zuerst eine Turnierart anlegen');
        $this->get(route('ressorts.index'))->assertOk()->assertSee('Noch keine Ressorts');
        $this->get(route('vorlagen.create'))->assertOk()->assertSee('Zuerst ein Ressort anlegen');

        $this->post(route('admin.hallen.speichern'), ['name' => 'Halle Altnau'])->assertSessionHasNoErrors();
        $this->post(route('admin.turniere.speichern'), ['name' => 'Hallencup Dezember', 'kurz' => 'DEZ', 'halle_id' => Halle::first()->id])->assertSessionHasNoErrors();
        $this->post(route('ressorts.store'), ['name' => 'Festwirtschaft', 'kuerzel' => 'FW'])->assertSessionHasNoErrors();
        $this->post(route('vorlagen.store'), ['titel' => 'Getränke bestellen', 'ressort_id' => Ressort::first()->id, 'tage_vorher' => 14, 'prioritaet' => 'normal'])->assertSessionHasNoErrors();
        $this->assertSame(1, Vorlage::count());

        $turnier = Turnier::first();
        $this->get(route('ausgaben.create', ['turnier_id' => $turnier->id, 'datum_von' => '2026-12-05']))->assertOk()->assertSee('Getränke bestellen');
        $this->post(route('ausgaben.store'), ['turnier_id' => $turnier->id, 'halle_id' => '', 'saison' => '2026/27', 'titel' => 'Hallencup Dezember 2026/27', 'datum_von' => '2026-12-05', 'auswahl' => [Vorlage::first()->id => ['aktion' => 'uebernehmen']]])->assertSessionHasNoErrors();
        $ausgabe = Ausgabe::first();
        $this->assertSame(1, $ausgabe->aufgaben()->count());
        $this->assertSame('2026-11-21', $ausgabe->aufgaben()->first()->faellig_am->format('Y-m-d'));

        foreach (['ausgaben.show', 'aufgaben.index', 'festwirtschaft.ausgabe', 'material.ausgabe'] as $r) {
            $this->get(route($r, $ausgabe))->assertOk();
        }
        $this->get(route('start'))->assertOk()->assertSee('5 von 6');
    }

    public function test_ausgabe_ohne_ressorts_und_vorlagen(): void
    {
        $this->withoutVite();
        $admin = User::create(['name' => 'Admin', 'email' => 'a@example.ch', 'password' => 'passwort123', 'rolle' => 'admin', 'aktiv' => true]);
        $turnier = Turnier::create(['name' => 'Cup', 'kurz' => 'CUP']);
        $this->actingAs($admin)->get(route('ausgaben.create', ['turnier_id' => $turnier->id, 'datum_von' => '2026-12-05']))->assertOk()->assertSee('Aufgaben gehören zu einem Ressort')->assertDontSee('zusatz.push');
        $this->actingAs($admin)->post(route('ausgaben.store'), ['turnier_id' => $turnier->id, 'saison' => '2026/27', 'titel' => 'Cup 2026', 'datum_von' => '2026-12-05'])->assertSessionHasNoErrors();
        $this->assertSame(1, Ausgabe::count());
        $this->actingAs($admin)->get(route('aufgaben.index', Ausgabe::first()))->assertOk()->assertSee('Zuerst ein Ressort anlegen');
    }
}
