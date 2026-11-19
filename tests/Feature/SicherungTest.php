<?php

namespace Tests\Feature;

use App\Models\Vorlage;
use App\Services\Sicherung;
use Illuminate\Support\Facades\Storage;

class SicherungTest extends OkTestCase
{
    public function test_sicherung_und_wiederherstellung(): void
    {
        Storage::fake('local');
        $s = app(Sicherung::class);
        $anzahl = Vorlage::count();
        $name = $s->erstelle('test');
        Vorlage::query()->delete();
        $this->assertSame(0, Vorlage::count());
        $s->wiederherstellen($name);
        $this->assertSame($anzahl, Vorlage::count());
    }

    public function test_vorlagen_export_und_import(): void
    {
        $admin = $this->person('admin');
        $json = $this->actingAs($admin)->get(route('admin.daten.vorlagen'))->assertOk()->streamedContent();
        $this->assertStringContainsString('ok-tool-vorlagen', $json);

        Vorlage::where('titel', 'Budget erstellen')->delete();
        $datei = \Illuminate\Http\UploadedFile::fake()->createWithContent('vorlagen.json', $json);
        $this->actingAs($admin)->post(route('admin.daten.vorlagen'), ['datei' => $datei])->assertSessionHas('erfolg');
        $this->assertTrue(Vorlage::where('titel', 'Budget erstellen')->exists());
    }
}
