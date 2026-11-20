<?php

namespace Tests\Feature;

use App\Models\Halle;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\User;
use App\Models\Vorlage;
use Illuminate\Support\Facades\Storage;

class LeerenTest extends OkTestCase
{
    public function test_leeren_behaelt_personen_und_sichert(): void
    {
        Storage::fake('local');
        $admin = $this->person('admin', 'GL', 'leitung');

        $this->actingAs($admin)->post(route('admin.daten.leeren'), ['bestaetigung' => 'falsch'])->assertSessionHasErrors('bestaetigung');
        $this->assertGreaterThan(0, Ressort::count());

        $this->actingAs($admin)->post(route('admin.daten.leeren'), ['bestaetigung' => 'LEEREN'])->assertRedirect(route('admin.einstellungen'));

        $this->assertSame(0, Ressort::count());
        $this->assertSame(0, Turnier::count());
        $this->assertSame(0, Halle::count());
        $this->assertSame(0, Vorlage::count());
        $this->assertTrue(User::whereKey($admin->id)->exists());
        $this->assertNotEmpty(Storage::disk('local')->allFiles());
    }

    public function test_nur_admin_darf_leeren(): void
    {
        $this->actingAs($this->person('mitglied'))->post(route('admin.daten.leeren'), ['bestaetigung' => 'LEEREN'])->assertForbidden();
        $this->assertGreaterThan(0, Ressort::count());
    }

    public function test_seiten_funktionieren_mit_leerem_tool(): void
    {
        Storage::fake('local');
        $this->artisan('ok:leeren', ['--force' => true])->assertSuccessful();
        $admin = $this->person('admin');
        $admin->update(['ansicht' => 'experte']);

        foreach (['start', 'suche', 'benachrichtigungen.index', 'ausgaben.index', 'ausgaben.create', 'vorlagen.index', 'vorlagen.create', 'ressorts.index', 'uebergaben.index', 'auswertung', 'admin.einstellungen', 'admin.daten', 'admin.system', 'admin.personen'] as $route) {
            $this->actingAs($admin->fresh())->get(route($route))->assertOk();
        }
        $admin->update(['ansicht' => 'einfach']);
        $this->actingAs($admin->fresh())->get(route('start'))->assertOk();
    }
}
