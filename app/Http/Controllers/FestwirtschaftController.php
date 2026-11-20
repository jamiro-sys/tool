<?php

namespace App\Http\Controllers;

use App\Models\Ausgabe;
use App\Models\Auswahlliste;
use App\Models\EinkaufPosten;
use App\Models\Kasse;
use App\Models\Produkt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FestwirtschaftController extends Controller
{
    /** Produktliste (gilt fuer alle Ausgaben) und Einstieg in die Ausgaben. */
    public function index()
    {
        return view('festwirtschaft.index', [
            'produkte' => Produkt::orderBy('kategorie')->orderBy('sortierung')->orderBy('name')->get(),
            'kategorien' => Auswahlliste::werte('produktkategorie'),
            'allergene' => Auswahlliste::werte('allergen'),
            'ausgaben' => Ausgabe::with('turnier')->orderByDesc('datum_von')->get(),
        ]);
    }

    public function preisliste()
    {
        return view('festwirtschaft.preisliste', [
            'gruppen' => Produkt::where('aktiv', true)->orderBy('sortierung')->orderBy('name')->get()->groupBy(fn ($p) => $p->kategorie ?: 'Weitere'),
        ]);
    }

    public function produkt(Request $request, ?Produkt $produkt = null)
    {
        Gate::authorize('schreiben');
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kategorie' => ['nullable', 'string', 'max:120'],
            'preis' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'einheit' => ['nullable', 'string', 'max:40'],
            'allergene' => ['nullable', 'array'],
            'allergene.*' => ['string', 'max:120'],
            'notizen' => ['nullable', 'string', 'max:2000'],
        ]);
        $daten['aktiv'] = $request->boolean('aktiv', true);
        $daten['allergene'] = array_values($daten['allergene'] ?? []);
        $produkt && $produkt->exists ? $produkt->update($daten) : Produkt::create($daten);

        return back()->with('erfolg', 'Produkt gespeichert.');
    }

    public function produktLoeschen(Produkt $produkt)
    {
        Gate::authorize('schreiben');
        $produkt->delete();

        return back()->with('erfolg', 'Produkt entfernt.');
    }

    /** Einkauf und Kassen einer Ausgabe. */
    public function ausgabe(Ausgabe $ausgabe)
    {
        $ausgabe->load('turnier', 'einkauf.produkt', 'kassen.verantwortlich');
        $vorher = $ausgabe->vorherige();

        return view('festwirtschaft.ausgabe', [
            'ausgabe' => $ausgabe,
            'vorher' => $vorher?->load('einkauf'),
            'produkte' => Produkt::where('aktiv', true)->orderBy('name')->get(),
            'personen' => User::where('aktiv', true)->orderBy('name')->get(),
        ]);
    }

    public function posten(Request $request, Ausgabe $ausgabe, ?EinkaufPosten $posten = null)
    {
        Gate::authorize('schreiben');
        abort_if($posten && $posten->exists && $posten->ausgabe_id !== $ausgabe->id, 404);
        $daten = $request->validate([
            'produkt_id' => ['nullable', 'exists:produkte,id'],
            'bezeichnung' => ['nullable', 'string', 'max:150'],
            'einheit' => ['nullable', 'string', 'max:40'],
            'bestellt' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'verbraucht' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lieferant' => ['nullable', 'string', 'max:150'],
            'notiz' => ['nullable', 'string', 'max:255'],
        ]);
        if (blank($daten['bezeichnung'] ?? null)) {
            $daten['bezeichnung'] = Produkt::find($daten['produkt_id'] ?? null)?->name;
        }
        if (blank($daten['bezeichnung'])) {
            return back()->withErrors(['bezeichnung' => 'Bitte ein Produkt wählen oder eine Bezeichnung eingeben.']);
        }
        $daten['eingekauft'] = $request->boolean('eingekauft');
        $posten && $posten->exists ? $posten->update($daten) : $ausgabe->einkauf()->create($daten);

        return back()->with('erfolg', 'Einkaufsposten gespeichert.');
    }

    public function postenLoeschen(Ausgabe $ausgabe, EinkaufPosten $posten)
    {
        Gate::authorize('schreiben');
        abort_if($posten->ausgabe_id !== $ausgabe->id, 404);
        $posten->delete();

        return back()->with('erfolg', 'Einkaufsposten entfernt.');
    }

    /** Einkaufsliste der letzten Ausgabe uebernehmen; Vorschlag = letzter Verbrauch. */
    public function uebernehmen(Ausgabe $ausgabe)
    {
        Gate::authorize('schreiben');
        $vorher = $ausgabe->vorherige();
        abort_unless($vorher, 404);
        $vorhanden = $ausgabe->einkauf()->pluck('bezeichnung')->map(fn ($b) => mb_strtolower($b))->all();
        $neu = 0;
        foreach ($vorher->einkauf as $alt) {
            if (in_array(mb_strtolower($alt->bezeichnung), $vorhanden, true)) {
                continue;
            }
            $ausgabe->einkauf()->create([
                'produkt_id' => $alt->produkt_id,
                'bezeichnung' => $alt->bezeichnung,
                'einheit' => $alt->einheit,
                'bestellt' => $alt->verbraucht ?? $alt->bestellt,
                'lieferant' => $alt->lieferant,
                'notiz' => $alt->notiz,
            ]);
            $neu++;
        }

        return back()->with('erfolg', $neu.' Posten aus '.$vorher->titel.' übernommen. Menge = letzter Verbrauch, bitte prüfen.');
    }

    public function kasse(Request $request, Ausgabe $ausgabe, ?Kasse $kasse = null)
    {
        Gate::authorize('schreiben');
        abort_if($kasse && $kasse->exists && $kasse->ausgabe_id !== $ausgabe->id, 404);
        $betrag = ['nullable', 'numeric', 'min:0', 'max:9999999'];
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'verantwortlich_id' => ['nullable', 'exists:users,id'],
            'start_bar' => $betrag, 'ende_bar' => $betrag, 'ende_twint' => $betrag, 'ende_karte' => $betrag,
            'gezaehlt_von' => ['nullable', 'string', 'max:120'],
            'zweite_person' => ['nullable', 'string', 'max:120'],
            'notiz' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($request->boolean('abschliessen')) {
            if (blank($daten['gezaehlt_von'] ?? null) || blank($daten['zweite_person'] ?? null)) {
                return back()->withErrors(['zweite_person' => 'Für den Abschluss braucht es zwei Personen, die gezählt haben.']);
            }
            $daten['abgeschlossen_am'] = now();
        }
        $kasse && $kasse->exists ? $kasse->update($daten) : $ausgabe->kassen()->create($daten);

        return back()->with('erfolg', $request->boolean('abschliessen') ? 'Kasse abgeschlossen.' : 'Kasse gespeichert.');
    }

    public function kasseLoeschen(Ausgabe $ausgabe, Kasse $kasse)
    {
        Gate::authorize('schreiben');
        abort_if($kasse->ausgabe_id !== $ausgabe->id, 404);
        $kasse->delete();

        return back()->with('erfolg', 'Kasse entfernt.');
    }
}
