<?php

namespace App\Http\Controllers;

use App\Models\Ausgabe;
use App\Models\Auswahlliste;
use App\Models\Material;
use App\Models\MaterialEinsatz;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MaterialController extends Controller
{
    /** Materialliste aller Ressorts mit Verlauf: wer hat was wann mitgebracht oder gemietet. */
    public function index(Request $request)
    {
        $alle = Material::with(['ressort', 'einsaetze.ausgabe', 'einsaetze.quellePerson'])->orderBy('name')->get();
        $ressortFilter = $request->query('ressort');
        $suche = trim((string) $request->query('q'));
        $liste = $alle
            ->when($ressortFilter === 'ohne', fn ($c) => $c->whereNull('ressort_id'))
            ->when($ressortFilter && $ressortFilter !== 'ohne', fn ($c) => $c->where('ressort_id', (int) $ressortFilter))
            ->when($suche !== '', fn ($c) => $c->filter(fn ($m) => str_contains(mb_strtolower($m->name.' '.$m->zweck.' '.$m->notizen), mb_strtolower($suche))));

        return view('material.index', [
            'gruppiert' => $liste->groupBy(fn ($m) => $m->ressort?->name ?? 'Ohne Ressort')->sortKeys(),
            'anzahl' => $alle->count(),
            'ressortFilter' => $ressortFilter,
            'suche' => $suche,
            'ressorts' => \App\Models\Ressort::orderBy('sortierung')->orderBy('name')->get(),
            'gruppen' => Auswahlliste::werte('materialgruppe'),
            'standorte' => Auswahlliste::werte('standort'),
            'ausgaben' => Ausgabe::with('turnier')->orderByDesc('datum_von')->get(),
            'reparieren' => $alle->where('zustand', '!=', 'gut')->count(),
            'kaufPruefen' => $alle->filter(fn ($m) => $m->mietBilanz()['pruefen'])->count(),
        ]);
    }

    public function speichern(Request $request, ?Material $material = null)
    {
        Gate::authorize('schreiben');
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'gruppe' => ['nullable', 'string', 'max:120'],
            'ressort_id' => ['nullable', 'exists:ressorts,id'],
            'zweck' => ['nullable', 'string', 'max:255'],
            'menge' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'einheit' => ['nullable', 'string', 'max:40'],
            'standort' => ['nullable', 'string', 'max:120'],
            'zustand' => ['required', Rule::in(array_keys(Material::ZUSTAND))],
            'eigentum' => ['required', Rule::in(array_keys(Material::EIGENTUM))],
            'eigentuemer' => ['nullable', 'string', 'max:150'],
            'notizen' => ['nullable', 'string', 'max:2000'],
            'foto' => ['nullable', 'image', 'max:8192'],
        ]);
        $daten['menge'] ??= 1;
        unset($daten['foto']);
        if ($request->hasFile('foto')) {
            if ($material?->foto_pfad) {
                Storage::disk('local')->delete($material->foto_pfad);
            }
            $daten['foto_pfad'] = $request->file('foto')->store('material', 'local');
        }
        $material && $material->exists ? $material->update($daten) : Material::create($daten);

        return back()->with('erfolg', 'Material gespeichert.');
    }

    public function loeschen(Material $material)
    {
        Gate::authorize('schreiben');
        if ($material->foto_pfad) {
            Storage::disk('local')->delete($material->foto_pfad);
        }
        $material->delete();

        return back()->with('erfolg', 'Material entfernt.');
    }

    public function foto(Material $material)
    {
        abort_unless($material->foto_pfad && Storage::disk('local')->exists($material->foto_pfad), 404);

        return Storage::disk('local')->response($material->foto_pfad);
    }

    /** Einsatzliste fuer Auf- und Abbau einer Ausgabe. */
    public function ausgabe(Ausgabe $ausgabe)
    {
        $ausgabe->load('turnier');
        $einsatz = $ausgabe->material()->with('material.ressort', 'verantwortlich', 'quellePerson')->get()->sortBy(fn ($e) => ($e->material->gruppe ?? 'zzz').$e->material->name);
        $vorher = $ausgabe->vorherige();

        return view('material.ausgabe', [
            'ausgabe' => $ausgabe,
            'einsatz' => $einsatz,
            'vorher' => $vorher,
            'vorherAnzahl' => $vorher ? $vorher->material()->count() : 0,
            'verfuegbar' => Material::with('ressort')->whereNotIn('id', $einsatz->pluck('material_id'))->orderBy('name')->get(),
            'personen' => User::where('aktiv', true)->orderBy('name')->get(),
        ]);
    }

    public function einsatz(Request $request, Ausgabe $ausgabe, ?MaterialEinsatz $einsatz = null)
    {
        Gate::authorize('schreiben');
        abort_if($einsatz && $einsatz->exists && $einsatz->ausgabe_id !== $ausgabe->id, 404);
        $daten = $request->validate([
            'material_id' => [$einsatz?->exists ? 'nullable' : 'required', 'exists:material,id'],
            'menge' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'herkunft' => ['nullable', Rule::in(array_keys(MaterialEinsatz::HERKUNFT))],
            'quelle_user_id' => ['nullable', 'exists:users,id'],
            'quelle' => ['nullable', 'string', 'max:150'],
            'kosten' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'rueckgabe_bis' => ['nullable', 'date'],
            'verantwortlich_id' => ['nullable', 'exists:users,id'],
            'notiz' => ['nullable', 'string', 'max:255'],
        ]);
        if ($einsatz && $einsatz->exists) {
            unset($daten['material_id']);
            $daten['herkunft'] ??= $einsatz->herkunft;
            $daten['menge'] ??= $einsatz->menge;
            $einsatz->update($daten);
        } else {
            $material = Material::findOrFail($daten['material_id']);
            $daten['menge'] ??= $material->menge;
            $daten['herkunft'] ??= ['verein' => 'verein', 'halle' => 'halle', 'geliehen' => 'geliehen'][$material->eigentum] ?? 'verein';
            $daten['quelle'] ??= $material->eigentum === 'geliehen' ? $material->eigentuemer : null;
            $ausgabe->material()->firstOrCreate(['material_id' => $material->id], $daten);
        }

        return back()->with('erfolg', 'Einsatzliste gespeichert.');
    }

    /** Haken setzen oder entfernen (auch per fetch vom Handy). */
    public function haken(Request $request, Ausgabe $ausgabe, MaterialEinsatz $einsatz)
    {
        Gate::authorize('schreiben');
        abort_if($einsatz->ausgabe_id !== $ausgabe->id, 404);
        $daten = $request->validate(['feld' => ['required', Rule::in(array_keys(MaterialEinsatz::HAKEN))]]);
        $einsatz->update([$daten['feld'] => ! $einsatz->{$daten['feld']}]);

        return $request->expectsJson()
            ? response()->json(['wert' => $einsatz->{$daten['feld']}])
            : back();
    }

    public function einsatzLoeschen(Ausgabe $ausgabe, MaterialEinsatz $einsatz)
    {
        Gate::authorize('schreiben');
        abort_if($einsatz->ausgabe_id !== $ausgabe->id, 404);
        $einsatz->delete();

        return back()->with('erfolg', 'Von der Einsatzliste entfernt.');
    }

    /** Einsatzliste der letzten Ausgabe uebernehmen, ohne Haken. */
    public function uebernehmen(Ausgabe $ausgabe)
    {
        Gate::authorize('schreiben');
        $vorher = $ausgabe->vorherige();
        abort_unless($vorher, 404);
        $neu = 0;
        foreach ($vorher->material as $alt) {
            $e = $ausgabe->material()->firstOrCreate(['material_id' => $alt->material_id], ['menge' => $alt->menge, 'herkunft' => $alt->herkunft, 'quelle_user_id' => $alt->quelle_user_id, 'quelle' => $alt->quelle, 'kosten' => $alt->kosten, 'notiz' => $alt->notiz]);
            $neu += $e->wasRecentlyCreated ? 1 : 0;
        }

        return back()->with('erfolg', $neu.' Positionen aus '.$vorher->titel.' übernommen.');
    }
}
