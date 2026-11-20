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
    public function index(Request $request)
    {
        $material = Material::orderBy('gruppe')->orderBy('name')->get();
        $filter = $request->query('gruppe');

        return view('material.index', [
            'material' => $filter ? $material->where('gruppe', $filter) : $material,
            'filter' => $filter,
            'alleGruppen' => $material->pluck('gruppe')->filter()->unique()->sort()->values(),
            'gruppen' => Auswahlliste::werte('materialgruppe'),
            'standorte' => Auswahlliste::werte('standort'),
            'ausgaben' => Ausgabe::with('turnier')->orderByDesc('datum_von')->get(),
            'reparieren' => $material->where('zustand', '!=', 'gut')->count(),
        ]);
    }

    public function speichern(Request $request, ?Material $material = null)
    {
        Gate::authorize('schreiben');
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'gruppe' => ['nullable', 'string', 'max:120'],
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
        $einsatz = $ausgabe->material()->with('material', 'verantwortlich')->get()->sortBy(fn ($e) => ($e->material->gruppe ?? 'zzz').$e->material->name);
        $vorher = $ausgabe->vorherige();

        return view('material.ausgabe', [
            'ausgabe' => $ausgabe,
            'einsatz' => $einsatz,
            'vorher' => $vorher,
            'vorherAnzahl' => $vorher ? $vorher->material()->count() : 0,
            'verfuegbar' => Material::whereNotIn('id', $einsatz->pluck('material_id'))->orderBy('name')->get(),
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
            'ausleihe' => ['nullable', 'string', 'max:150'],
            'rueckgabe_bis' => ['nullable', 'date'],
            'verantwortlich_id' => ['nullable', 'exists:users,id'],
            'notiz' => ['nullable', 'string', 'max:255'],
        ]);
        if ($einsatz && $einsatz->exists) {
            unset($daten['material_id']);
            $daten['menge'] ??= $einsatz->menge;
            $einsatz->update($daten);
        } else {
            $material = Material::findOrFail($daten['material_id']);
            $daten['menge'] ??= $material->menge;
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
            $e = $ausgabe->material()->firstOrCreate(['material_id' => $alt->material_id], ['menge' => $alt->menge, 'ausleihe' => $alt->ausleihe, 'notiz' => $alt->notiz]);
            $neu += $e->wasRecentlyCreated ? 1 : 0;
        }

        return back()->with('erfolg', $neu.' Positionen aus '.$vorher->titel.' übernommen.');
    }
}
