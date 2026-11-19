<?php

namespace Tests\Feature;

use App\Models\Ausgabe;
use App\Models\Aufgabe;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Notifications\Hinweis;
use Illuminate\Support\Facades\Notification;

class RechteUndAufgabenTest extends OkTestCase
{
    protected function aufgabe(string $ressort = 'FIN', $verantwortlich = null): Aufgabe
    {
        $ausgabe = Ausgabe::create(['turnier_id' => Turnier::first()->id, 'saison' => '2026/27', 'titel' => 'X', 'datum_von' => now()->addDays(20)]);

        return Aufgabe::create(['ausgabe_id' => $ausgabe->id, 'ressort_id' => Ressort::where('kuerzel', $ressort)->value('id'), 'titel' => 'Test', 'faellig_am' => now()->addDays(3), 'verantwortlich_id' => $verantwortlich?->id]);
    }

    public function test_gaeste_werden_zur_anmeldung_geschickt(): void
    {
        $this->get('/')->assertRedirect(route('anmelden'));
    }

    public function test_anmeldung_und_gesperrter_zugang(): void
    {
        $u = $this->person();
        $this->post(route('anmelden'), ['email' => $u->email, 'password' => 'passwort123'])->assertRedirect(route('start'));
        auth()->logout();
        $u->update(['aktiv' => false]);
        $this->post(route('anmelden'), ['email' => $u->email, 'password' => 'passwort123'])->assertSessionHasErrors('email');
    }

    public function test_mitglied_im_ressort_kann_abhaken_fremde_nicht(): void
    {
        $a = $this->aufgabe('FIN');
        $this->actingAs($this->person('mitglied', 'SPO'))->postJson(route('aufgaben.status', $a), ['status' => 'erledigt'])->assertForbidden();
        $this->actingAs($this->person('mitglied', 'FIN'))->postJson(route('aufgaben.status', $a), ['status' => 'erledigt'])->assertOk()->assertJsonPath('fortschritt.prozent', 100);
        $this->assertNotNull($a->fresh()->erledigt_am);
    }

    public function test_lesend_darf_nichts_aendern(): void
    {
        $leser = $this->person('lesend', 'FIN');
        $a = $this->aufgabe('FIN', $leser);
        $this->actingAs($leser)->postJson(route('aufgaben.status', $a), ['status' => 'erledigt'])->assertForbidden();
        $this->actingAs($leser)->get(route('aufgaben.show', $a))->assertOk();
    }

    public function test_zuweisung_loest_benachrichtigung_aus(): void
    {
        Notification::fake();
        $leitung = $this->person('mitglied', 'FIN', 'leitung');
        $ziel = $this->person('mitglied', 'FIN');
        $a = $this->aufgabe('FIN');
        $this->actingAs($leitung)->patch(route('aufgaben.update', $a), ['verantwortlich_id' => $ziel->id])->assertRedirect();
        Notification::assertSentTo($ziel, Hinweis::class, fn ($n) => $n->ereignis === 'zugewiesen');
        Notification::assertNotSentTo($leitung, Hinweis::class);
    }

    public function test_kanaele_und_ruhezeit(): void
    {
        $u = $this->person();
        $u->update(['benachrichtigungen' => ['zugewiesen' => ['app']], 'ruhe_von' => '22:00', 'ruhe_bis' => '07:00']);
        $n = new Hinweis('zugewiesen', 'T', 'x', null, $u->kanaeleFuer('zugewiesen'));
        $this->assertSame(['database'], $n->via($u));
        $this->assertTrue($u->inRuhezeit(now()->setTime(23, 30)));
        $this->assertFalse($u->inRuhezeit(now()->setTime(12, 0)));
    }

    public function test_erinnerungen_werden_nur_einmal_pro_tag_versendet(): void
    {
        Notification::fake();
        $u = $this->person('mitglied', 'FIN');
        $a = $this->aufgabe('FIN', $u);
        $this->artisan('ok:erinnerungen')->assertSuccessful();
        $this->artisan('ok:erinnerungen')->assertSuccessful();
        Notification::assertSentToTimes($u, Hinweis::class, 1);
        $this->assertNotNull($a->fresh()->erinnert_am);
    }

    public function test_admin_bereich_nur_fuer_admin(): void
    {
        $this->actingAs($this->person())->get(route('admin.system'))->assertForbidden();
        $this->actingAs($this->person('admin'))->get(route('admin.system'))->assertOk();
    }
}
