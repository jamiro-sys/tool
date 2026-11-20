<?php

namespace Tests\Feature;

use App\Models\Dokument;
use App\Models\Ressort;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DateienTest extends OkTestCase
{
    public function test_hochladen_zuteilen_filtern_und_beim_ressort_sehen(): void
    {
        Storage::fake('local');
        $ressort = Ressort::first();
        $m = $this->person('mitglied', $ressort->kuerzel);

        $this->actingAs($m)->get(route('dateien.index'))->assertOk()->assertSee('Noch keine Dateien');
        $this->actingAs($m)->post(route('dateien.speichern'), [
            'ressort_id' => $ressort->id,
            'dateien' => [UploadedFile::fake()->create('Reglement 2026.pdf', 120, 'application/pdf'), UploadedFile::fake()->image('Hallenplan.png')],
        ])->assertSessionHasNoErrors();
        $this->actingAs($m)->post(route('dateien.speichern'), ['url' => 'https://example.ch/plan', 'titel' => 'Plan online'])->assertSessionHasNoErrors();
        $this->actingAs($m)->post(route('dateien.speichern'), [])->assertSessionHasErrors();

        $this->assertSame(3, Dokument::count());
        $pdf = Dokument::where('titel', 'Reglement 2026')->first();
        $this->assertSame('PDF', $pdf->endung());
        $this->assertNotNull($pdf->groesseText());

        $this->actingAs($m)->get(route('dateien.index', ['ressort' => $ressort->id]))->assertSee('Reglement 2026')->assertDontSee('Plan online');
        $this->actingAs($m)->get(route('dateien.index', ['ressort' => 'allgemein']))->assertSee('Plan online')->assertDontSee('Reglement 2026');
        $this->actingAs($m)->get(route('dateien.index', ['q' => 'hallen']))->assertSee('Hallenplan')->assertDontSee('Reglement 2026');
        $this->actingAs($m)->get(route('ressorts.show', $ressort))->assertSee('Reglement 2026');
        $this->actingAs($m)->get(route('dokumente.download', [$pdf, 'ansehen' => 1]))->assertOk();

        $this->actingAs($m)->patch(route('dateien.aendern', $pdf), ['titel' => 'Turnierreglement', 'ressort_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($pdf->fresh()->ressort_id);
    }

    public function test_rechte_beim_loeschen_und_hochladen(): void
    {
        Storage::fake('local');
        $autor = $this->person('mitglied');
        $this->actingAs($autor)->post(route('dateien.speichern'), ['dateien' => [UploadedFile::fake()->create('a.txt', 1)]]);
        $d = Dokument::first();

        $this->actingAs($this->person('lesend'))->post(route('dateien.speichern'), ['url' => 'https://example.ch'])->assertForbidden();
        $this->actingAs($this->person('mitglied'))->delete(route('dokumente.loeschen', $d))->assertForbidden();
        $this->actingAs($autor)->delete(route('dokumente.loeschen', $d))->assertRedirect();
        $this->assertSame(0, Dokument::count());
    }
}
