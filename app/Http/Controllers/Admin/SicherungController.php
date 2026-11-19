<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aktivitaet;
use App\Models\Einstellung;
use App\Services\Sicherung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SicherungController extends Controller
{
    public function __construct(protected Sicherung $sicherung) {}

    public function index()
    {
        return view('admin.sicherungen', [
            'sicherungen' => $this->sicherung->liste(),
            'aufbewahrung' => Einstellung::wert('backup_aufbewahrung'),
        ]);
    }

    public function erstellen()
    {
        $name = $this->sicherung->erstelle('manuell');
        Aktivitaet::protokolliere('sicherung.erstellt', 'Sicherung erstellt: '.$name);

        return back()->with('erfolg', 'Sicherung erstellt.');
    }

    public function herunterladen(string $name)
    {
        return Storage::disk('local')->download($this->sicherung->pfad($name));
    }

    public function wiederherstellen(Request $request, string $name)
    {
        $request->validate(['bestaetigung' => ['required', 'in:WIEDERHERSTELLEN']], ['bestaetigung.in' => 'Bitte WIEDERHERSTELLEN eintippen.']);
        $vorher = $this->sicherung->erstelle('vor-wiederherstellung');
        $zeilen = $this->sicherung->wiederherstellen($name);
        Aktivitaet::protokolliere('sicherung.wiederhergestellt', 'Sicherung '.$name.' eingespielt ('.$zeilen.' Zeilen), vorher gesichert als '.$vorher);

        return back()->with('erfolg', 'Sicherung eingespielt ('.$zeilen.' Zeilen). Der vorherige Stand ist als '.$vorher.' gesichert.');
    }

    public function hochladen(Request $request)
    {
        $request->validate(['datei' => ['required', 'file', 'max:51200']]);
        $name = 'sicherung-'.now()->format('Y-m-d-His').'-hochgeladen.json.gz';
        $inhalt = file_get_contents($request->file('datei')->getRealPath());
        $json = @gzdecode($inhalt) ?: $inhalt;
        abort_unless(is_array(json_decode($json, true)['tabellen'] ?? null), 422, 'Das ist keine gültige Sicherung.');
        Storage::disk('local')->put(Sicherung::ORDNER.'/'.$name, gzencode($json, 6));

        return back()->with('erfolg', 'Sicherung hochgeladen. Du kannst sie jetzt einspielen.');
    }

    public function loeschen(string $name)
    {
        Storage::disk('local')->delete($this->sicherung->pfad($name));

        return back()->with('erfolg', 'Sicherung gelöscht.');
    }
}
