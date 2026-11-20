<?php

namespace Tests\Feature;

class StyleguideTest extends OkTestCase
{
    public function test_styleguide_fuer_alle_und_in_dateien_verlinkt(): void
    {
        $m = $this->person('lesend');
        $this->actingAs($m)->get(route('styleguide'))->assertOk()->assertSee('#B8161C')->assertSee('Plus Jakarta Sans')->assertSee('Bausteine im OK-Tool')->assertSee('Als PDF herunterladen');
        $this->actingAs($m)->get(route('dateien.index'))->assertSee(route('styleguide'))->assertSee('FCM-Styleguide');
        auth()->logout();
        $this->get(route('styleguide'))->assertRedirect(route('anmelden'));
    }
}
