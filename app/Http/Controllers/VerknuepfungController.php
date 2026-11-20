<?php

namespace App\Http\Controllers;

use App\Models\Aktivitaet;
use App\Models\Ausgabe;
use App\Models\Auswahlliste;
use App\Models\Kennzahl;
use App\Models\Verknuepfung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class VerknuepfungController extends Controller
{
    public function speichern(Request $request)
    {
        Gate::authorize('schreiben');
        $daten = $request->validate([
            'bezug_typ' => ['required', Rule::in(array_keys(Verknuepfung::BEZUEGE))],
            'bezug_id' => ['required', 'integer'],
            'titel' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url:http,https', 'max:500'],
            'werkzeug' => ['nullable', Rule::in(array_keys(Verknuepfung::WERKZEUGE))],
        ]);
        $bezug = (Verknuepfung::BEZUEGE[$daten['bezug_typ']])::findOrFail($daten['bezug_id']);
        $link = $bezug->verknuepfungen()->create($daten);
        Aktivitaet::protokolliere('link.neu', 'Link «'.$link->titel.'» hinzugefügt', $bezug);

        return back()->with('erfolg', 'Link gespeichert.');
    }

    public function loeschen(Verknuepfung $verknuepfung)
    {
        Gate::authorize('schreiben');
        $verknuepfung->delete();

        return back()->with('erfolg', 'Link entfernt.');
    }

    public function kennzahlen(Request $request, Ausgabe $ausgabe)
    {
        Gate::authorize('schreiben');
        $namen = Auswahlliste::werte('kennzahl')->all();
        $daten = $request->validate([
            'werte' => ['array'],
            'werte.*' => ['nullable', 'numeric', 'min:-999999999', 'max:999999999'],
        ]);
        foreach ($daten['werte'] ?? [] as $name => $wert) {
            if (! in_array($name, $namen, true)) {
                continue;
            }
            if ($wert === null || $wert === '') {
                $ausgabe->kennzahlen()->where('name', $name)->delete();
            } else {
                Kennzahl::updateOrCreate(['ausgabe_id' => $ausgabe->id, 'name' => $name], ['wert' => $wert]);
            }
        }

        return back()->with('erfolg', 'Kennzahlen gespeichert.');
    }
}
