<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aktivitaet;
use App\Models\Einstellung;
use App\Models\Halle;
use App\Models\Turnier;
use Illuminate\Http\Request;

class EinstellungenController extends Controller
{
    public function index()
    {
        return view('admin.einstellungen', [
            'werte' => collect(Einstellung::STANDARD)->mapWithKeys(fn ($s, $k) => [$k => Einstellung::wert($k, $s)]),
            'hallen' => Halle::orderBy('name')->get(),
            'turniere' => Turnier::with('halle')->get(),
            'listen' => \App\Models\Auswahlliste::orderBy('sortierung')->orderBy('wert')->get()->groupBy('bereich'),
        ]);
    }

    public function speichern(Request $request)
    {
        $daten = $request->validate([
            'verein_name' => ['required', 'string', 'max:100'],
            'tool_name' => ['required', 'string', 'max:100'],
            'erinnerung_tage' => ['required', 'integer', 'min:0', 'max:30'],
            'backup_aufbewahrung' => ['required', 'integer', 'min:1', 'max:365'],
            'wochenmail_tag' => ['required', 'integer', 'min:1', 'max:7'],
        ]);
        foreach ($daten as $k => $w) {
            Einstellung::setze($k, is_numeric($w) ? (int) $w : $w);
        }
        foreach (['funktion_push', 'funktion_wochenmail'] as $schalter) {
            Einstellung::setze($schalter, $request->boolean($schalter));
        }
        Aktivitaet::protokolliere('einstellungen', 'Einstellungen gespeichert');

        return back()->with('erfolg', 'Einstellungen gespeichert.');
    }

    public function halle(Request $request, ?Halle $halle = null)
    {
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'ort' => ['nullable', 'string', 'max:100'],
            'adresse' => ['nullable', 'string', 'max:200'],
            'hallenwart' => ['nullable', 'string', 'max:100'],
            'telefon' => ['nullable', 'string', 'max:50'],
            'notizen' => ['nullable', 'string', 'max:5000'],
        ]);
        $halle && $halle->exists ? $halle->update($daten) : Halle::create($daten);

        return back()->with('erfolg', 'Halle gespeichert.');
    }

    public function turnier(Request $request, ?Turnier $turnier = null)
    {
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'kurz' => ['required', 'string', 'max:12'],
            'halle_id' => ['nullable', 'exists:hallen,id'],
            'beschreibung' => ['nullable', 'string', 'max:5000'],
        ]);
        $turnier && $turnier->exists ? $turnier->update($daten) : Turnier::create($daten);

        return back()->with('erfolg', 'Turnier gespeichert.');
    }
}
