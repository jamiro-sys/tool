<?php

namespace App\Http\Controllers;

use App\Models\Aktivitaet;
use App\Models\Aufgabe;
use App\Models\Ausgabe;
use App\Models\Ressort;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RessortController extends Controller
{
    public function index()
    {
        $ressorts = Ressort::with(['mitglieder', 'vorlagen', 'kontakte', 'wissen'])->orderBy('sortierung')->get();
        $ausgabe = Ausgabe::naechste();
        $fortschritt = $ausgabe ? StartController::ressortFortschritt($ausgabe)->keyBy(fn ($e) => $e['ressort']->id) : collect();

        return view('ressorts.index', compact('ressorts', 'ausgabe', 'fortschritt'));
    }

    public function show(Request $request, Ressort $ressort)
    {
        $tab = in_array($request->query('tab'), ['uebersicht', 'auftrag', 'personen', 'aufgaben', 'jahreslauf', 'kontakte', 'wissen', 'material', 'dateien', 'erkenntnisse'], true) ? $request->query('tab') : 'uebersicht';
        $ressort->load(['mitglieder', 'vorlagen.turniere', 'kontakte', 'dokumente.user', 'dokumente.ordner', 'wissen.user', 'uebergaben.von', 'uebergaben.an', 'material.einsaetze']);
        $ausgabe = Ausgabe::naechste();

        $offen = $ausgabe ? $ressort->aufgaben()->with('verantwortlich')->where('ausgabe_id', $ausgabe->id)->offen()->orderByRaw('faellig_am is null')->orderBy('faellig_am')->get() : collect();

        // Jahreskalender: Vorlagen nach Monaten vor dem Turnier
        $kalender = $ressort->vorlagen->where('aktiv', true)->sortByDesc('tage_vorher')->groupBy(function ($v) {
            $t = $v->tage_vorher;

            return match (true) {
                $t > 120 => 'Mehr als 4 Monate vorher',
                $t > 60 => '2 bis 4 Monate vorher',
                $t > 28 => '1 bis 2 Monate vorher',
                $t > 7 => '1 bis 4 Wochen vorher',
                $t > 0 => 'Turnierwoche',
                $t === 0 => 'Turniertag',
                default => 'Nach dem Turnier',
            };
        });

        $aufwandJahre = Aufgabe::where('ressort_id', $ressort->id)->with('ausgabe')->get()
            ->groupBy(fn ($a) => $a->ausgabe->titel)
            ->map(fn ($g) => ['geplant' => round($g->sum('aufwand_geplant'), 1), 'effektiv' => round($g->sum('aufwand_effektiv'), 1), 'datum' => $g->first()->ausgabe->datum_von])
            ->sortBy('datum')->take(-8);

        $erkenntnisse = $ressort->erkenntnisse()->with('user', 'ausgabe', 'vorlage')->latest()->limit(20)->get();

        return view('ressorts.show', [
            'ressorts' => Ressort::orderBy('sortierung')->orderBy('name')->get(),
            'gruppen' => \App\Models\Auswahlliste::werte('materialgruppe'),
            'standorte' => \App\Models\Auswahlliste::werte('standort'),
            'ressort' => $ressort,
            'tab' => $tab,
            'alleOrdner' => \App\Models\Ordner::orderBy('sortierung')->orderBy('name')->get(),
            'ausgabe' => $ausgabe,
            'offen' => $offen,
            'kalender' => $kalender,
            'aufwandJahre' => $aufwandJahre,
            'erkenntnisse' => $erkenntnisse,
            'wissensstand' => $ressort->wissensstand(),
            'personen' => User::where('aktiv', true)->orderBy('name')->get(),
        ]);
    }

    public function dossier(Ressort $ressort)
    {
        $ressort->load(['mitglieder', 'vorlagen' => fn ($q) => $q->where('aktiv', true)->orderByDesc('tage_vorher'), 'vorlagen.turniere', 'kontakte', 'dokumente', 'wissen']);
        $erkenntnisse = $ressort->erkenntnisse()->with('ausgabe', 'vorlage')->latest()->limit(40)->get();
        $offen = Aufgabe::where('ressort_id', $ressort->id)->offen()->whereHas('ausgabe', fn ($q) => $q->where('status', '!=', 'abgeschlossen'))->with('ausgabe', 'verantwortlich')->orderBy('faellig_am')->get();

        return view('ressorts.dossier', compact('ressort', 'erkenntnisse', 'offen'));
    }

    public function store(Request $request)
    {
        Gate::authorize('admin');
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'kuerzel' => ['required', 'string', 'max:8'],
            'auftrag' => ['nullable', 'string', 'max:5000'],
        ]);
        $ressort = Ressort::create($daten + ['sortierung' => (int) Ressort::max('sortierung') + 1]);
        Aktivitaet::protokolliere('ressort.erstellt', 'Ressort erstellt: '.$ressort->name, $ressort);

        return redirect()->route('ressorts.show', $ressort)->with('erfolg', 'Ressort erstellt.');
    }

    public function update(Request $request, Ressort $ressort)
    {
        Gate::authorize('ressort-verwalten', $ressort);
        $daten = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'kuerzel' => ['sometimes', 'string', 'max:8'],
            'auftrag' => ['nullable', 'string', 'max:5000'],
            'sortierung' => ['sometimes', 'integer'],
        ]);
        if (! $request->user()->istAdmin()) {
            unset($daten['sortierung']);
        }
        $ressort->update($daten);

        return back()->with('erfolg', 'Ressort gespeichert.');
    }

    public function mitglied(Request $request, Ressort $ressort)
    {
        Gate::authorize('ressort-verwalten', $ressort);
        $daten = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'rolle' => ['required', Rule::in(['leitung', 'mitglied', 'nachfolge', 'entfernen'])],
        ]);
        // Leitung darf nur der Admin oder ueber eine Uebergabe vergeben werden.
        if ($daten['rolle'] === 'leitung' && ! $request->user()->istAdmin()) {
            abort(403, 'Die Leitung wird über eine Übergabe gewechselt.');
        }

        if ($daten['rolle'] === 'entfernen') {
            $ressort->mitglieder()->detach($daten['user_id']);
        } else {
            // Neue Mitglieder bekommen "seit heute", bestehende behalten ihr Eintrittsdatum
            if ($ressort->mitglieder()->where('users.id', $daten['user_id'])->exists()) {
                $ressort->mitglieder()->updateExistingPivot($daten['user_id'], ['rolle' => $daten['rolle']]);
            } else {
                $ressort->mitglieder()->attach($daten['user_id'], ['rolle' => $daten['rolle'], 'seit' => now()]);
            }
        }
        $person = User::find($daten['user_id']);
        Aktivitaet::protokolliere('ressort.mitglied', $ressort->name.': '.$person->name.' '.$daten['rolle'], $ressort);

        return back()->with('erfolg', 'Mitglieder aktualisiert.');
    }
}
