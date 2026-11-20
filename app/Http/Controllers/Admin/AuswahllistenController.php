<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auswahlliste;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuswahllistenController extends Controller
{
    public function speichern(Request $request)
    {
        $daten = $request->validate([
            'bereich' => ['required', Rule::in(array_keys(Auswahlliste::BEREICHE))],
            'wert' => ['required', 'string', 'max:120',
                Rule::unique('auswahllisten')->where('bereich', $request->input('bereich'))],
        ], ['wert.unique' => 'Diesen Eintrag gibt es schon.']);
        $daten['sortierung'] = (int) Auswahlliste::where('bereich', $daten['bereich'])->max('sortierung') + 1;
        Auswahlliste::create($daten);

        return back()->with('erfolg', 'Eintrag hinzugefügt.');
    }

    public function loeschen(Auswahlliste $eintrag)
    {
        $eintrag->delete();

        return back()->with('erfolg', 'Eintrag entfernt. Bereits erfasste Angaben bleiben bestehen.');
    }
}
