<?php

namespace Tests\Feature;

use App\Models\Ressort;

class NavigationTest extends OkTestCase
{
    public function test_seitenleiste_mit_grundmenues_und_ressorts(): void
    {
        $seite = $this->actingAs($this->person('mitglied'))->get(route('start'))->assertOk();
        $seite->assertSee('Turniere')->assertSee(Ressort::first()->name)->assertDontSee('Festwirtschaft</a>', false);
        $seite->assertDontSee(route('uebergaben.index'))->assertSee(route('vorlagen.index'))->assertSee('Jahresplan');

        $admin = $this->actingAs($this->person('admin'))->get(route('start'))->assertOk();
        $admin->assertSee(route('uebergaben.index'))->assertSee(route('vorlagen.index'))->assertSee(route('auswertung'))->assertSee(route('material.index'));
    }

    public function test_uebergaben_uebersicht_nur_fuer_admin(): void
    {
        $this->actingAs($this->person('mitglied'))->get(route('uebergaben.index'))->assertForbidden();
        $this->actingAs($this->person('admin'))->get(route('uebergaben.index'))->assertOk();
    }

    public function test_push_schluessel_werden_bereinigt(): void
    {
        $k = 'BPqnUzwvPjM0YQb7pS5Nn3o5BR3a1HYk3yVv5t1oKoe8xJmQ7nWl9ZcR0sT2uV4wX6yZ8aB0cD2eF4gH6iJ8kL0';
        config(['services.webpush.public_key' => preg_replace('/^\s*VAPID_PUBLIC_KEY\s*=\s*|[\s\'"]+/', '', "VAPID_PUBLIC_KEY=\"$k\"\n")]);
        $this->assertSame($k, config('services.webpush.public_key'));
        config(['services.webpush.public_key' => 'ungueltig', 'services.webpush.private_key' => 'x']);
        $this->assertFalse(\App\Channels\WebPushKanal::konfiguriert());
        $echt = \Minishlink\WebPush\VAPID::createVapidKeys();
        config(['services.webpush.public_key' => $echt['publicKey'], 'services.webpush.private_key' => $echt['privateKey']]);
        $this->assertTrue(\App\Channels\WebPushKanal::konfiguriert());
    }

    public function test_ressort_steckbrief_mit_uebersicht_und_reitern(): void
    {
        $r = Ressort::first();
        $r->update(['auftrag' => 'Sorgt fuer QQQ-Auftrag']);
        $r->kontakte()->create(['name' => 'Hallenwart QQQ']);
        $r->wissen()->create(['titel' => 'Ablauf QQQ', 'inhalt' => 'Text']);
        $m = $this->person('mitglied', $r->kuerzel);

        $ueb = $this->actingAs($m)->get(route('ressorts.show', $r))->assertOk();
        $ueb->assertSee('Übersicht')->assertSee('aria-current="page"', false)->assertSee('Sorgt fuer QQQ-Auftrag')->assertSee('Als Nächstes')->assertSee('Übergabe-Bereitschaft');
        foreach (['auftrag', 'personen', 'aufgaben', 'jahreslauf', 'kontakte', 'wissen', 'material', 'dateien', 'erkenntnisse'] as $tab) {
            $ueb->assertSee(route('ressorts.show', [$r, 'tab' => $tab]), false);
            $this->actingAs($m)->get(route('ressorts.show', [$r, 'tab' => $tab]))->assertOk();
        }
        $this->actingAs($m)->get(route('ressorts.show', [$r, 'tab' => 'kontakte']))->assertSee('Hallenwart QQQ')->assertDontSee('Ablauf QQQ');
        $this->actingAs($m)->get(route('ressorts.show', [$r, 'tab' => 'wissen']))->assertSee('Ablauf QQQ')->assertDontSee('Hallenwart QQQ');
        $this->actingAs($m)->get(route('ressorts.show', [$r, 'tab' => 'unsinn']))->assertOk()->assertSee('Als Nächstes');

        // Nach dem Speichern zurueck auf den richtigen Reiter
        $this->actingAs($m)->post(route('kontakte.speichern', $r), ['name' => 'Neu QQQ'])->assertRedirect(route('ressorts.show', [$r, 'tab' => 'kontakte']));
    }
}
