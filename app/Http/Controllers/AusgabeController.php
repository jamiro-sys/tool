<?php

namespace App\Http\Controllers;

use App\Models\Aktivitaet;
use App\Models\Ausgabe;
use App\Models\Erkenntnis;
use App\Models\Halle;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\User;
use App\Services\AusgabePlaner;
use App\Services\Benachrichtiger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AusgabeController extends Controller
{
    public function __construct(protected AusgabePlaner $planer, protected Benachrichtiger $benachrichtiger) {}

    public function index()
    {
        $ausgaben = Ausgabe::with('turnier', 'halle')->withCount([
            'aufgaben as total' => fn ($q) => $q->where('status', '!=', 'entfaellt'),
            'aufgaben as erledigt' => fn ($q) => $q->where('status', 'erledigt'),
        ])->orderByDesc('datum_von')->get();

        return view('ausgaben.index', ['ausgaben' => $ausgaben, 'turniere' => Turnier::with('halle')->get()]);
    }

    public function create(Request $request)
    {
        Gate::authorize('ausgaben-verwalten');
        $turniere = Turnier::with(['halle', 'ausgaben'])->get();
        $hallen = Halle::orderBy('name')->get();

        $turnier = $request->filled('turnier_id') ? Turnier::find($request->integer('turnier_id')) : null;
        $vorschlag = null;
        $datum = null;
        if ($turnier && $request->filled('datum_von')) {
            $datum = Carbon::parse($request->query('datum_von'));
            $bis = $request->filled('datum_bis') ? Carbon::parse($request->query('datum_bis')) : null;
            $vorschlag = $this->planer->vorschlag($turnier, $datum, $bis)->groupBy(fn ($e) => $e['vorlage']->ressort_id);
        }

        return view('ausgaben.create', [
            'turniere' => $turniere,
            'hallen' => $hallen,
            'turnier' => $turnier,
            'datum' => $datum,
            'vorschlag' => $vorschlag,
            'ressorts' => Ressort::orderBy('sortierung')->get()->keyBy('id'),
            'personen' => User::where('aktiv', true)->orderBy('name')->get(),
            'eingabe' => $request->only(['turnier_id', 'datum_von', 'datum_bis', 'saison', 'titel', 'halle_id']),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('ausgaben-verwalten');
        $daten = $request->validate([
            'turnier_id' => ['required', 'exists:turniere,id'],
            'halle_id' => ['nullable', 'exists:hallen,id'],
            'saison' => ['required', 'string', 'max:16'],
            'titel' => ['required', 'string', 'max:200'],
            'datum_von' => ['required', 'date'],
            'datum_bis' => ['nullable', 'date', 'after_or_equal:datum_von'],
            'auswahl' => ['array'],
            'auswahl.*.aktion' => ['nullable', Rule::in(['uebernehmen', 'weglassen'])],
            'auswahl.*.titel' => ['nullable', 'string', 'max:200'],
            'auswahl.*.faellig_am' => ['nullable', 'date'],
            'auswahl.*.verantwortlich_id' => ['nullable', 'exists:users,id'],
            'zusatz' => ['array'],
            'zusatz.*.titel' => ['nullable', 'string', 'max:200'],
            'zusatz.*.ressort_id' => ['nullable', 'exists:ressorts,id'],
            'zusatz.*.faellig_am' => ['nullable', 'date'],
            'zusatz.*.verantwortlich_id' => ['nullable', 'exists:users,id'],
        ]);

        $turnier = Turnier::findOrFail($daten['turnier_id']);
        $ausgabe = $this->planer->erstelle($turnier, $daten, $daten['auswahl'] ?? [], $daten['zusatz'] ?? []);

        $this->benachrichtiger->sende(
            User::where('aktiv', true)->get(), 'ausgabe', 'Neues Turnier: '.$ausgabe->titel,
            'Die Planung für '.$ausgabe->datumText().' hat begonnen. Schau dir deine Aufgaben an.',
            route('ausgaben.show', $ausgabe), $request->user()
        );

        return redirect()->route('ausgaben.show', $ausgabe)->with('erfolg', 'Turnier erstellt. Viel Erfolg bei der Planung!');
    }

    public function show(Ausgabe $ausgabe)
    {
        $ausgabe->load('turnier', 'halle', 'aufgaben.ressort', 'aufgaben.verantwortlich', 'kennzahlen', 'verknuepfungen');
        $vorher = $ausgabe->vorherige()?->load('kennzahlen');
        $ressorts = StartController::ressortFortschritt($ausgabe);

        // Burndown: offene Aufgaben je Woche bis zum Turnier
        $aufgaben = $ausgabe->aufgaben->where('status', '!=', 'entfaellt');
        $start = ($aufgaben->min('faellig_am') ?? $ausgabe->datum_von)->copy()->startOfWeek();
        $ende = $ausgabe->datum_von->copy()->endOfWeek();
        if ($start->diffInWeeks($ende) > 30) {
            $start = $ende->copy()->subWeeks(30)->startOfWeek();
        }
        $burndown = ['labels' => [], 'soll' => [], 'ist' => []];
        for ($w = $start->copy(); $w->lte($ende); $w->addWeek()) {
            $stichtag = $w->copy()->endOfWeek();
            $burndown['labels'][] = $w->format('d.m.');
            $burndown['soll'][] = $aufgaben->filter(fn ($a) => ! $a->faellig_am || $a->faellig_am->gt($stichtag))->count();
            $burndown['ist'][] = $stichtag->isFuture() && ! $stichtag->isCurrentWeek()
                ? null
                : $aufgaben->filter(fn ($a) => ! $a->erledigt_am || $a->erledigt_am->gt($stichtag))->count();
        }

        $statusVerteilung = collect(\App\Models\Aufgabe::STATUS)->map(fn ($l, $k) => $ausgabe->aufgaben->where('status', $k)->count());

        return view('ausgaben.show', [
            'ausgabe' => $ausgabe,
            'vorher' => $vorher,
            'kennzahlNamen' => \App\Models\Auswahlliste::werte('kennzahl'),
            'ressorts' => $ressorts,
            'burndown' => $burndown,
            'statusVerteilung' => $statusVerteilung,
            'fortschritt' => $ausgabe->fortschritt(),
            'bald' => $ausgabe->aufgaben->filter->istOffen()->filter(fn ($a) => $a->faellig_am && $a->faellig_am->lte(today()->addDays(14)))->sortBy('faellig_am')->take(10),
            'ohneZuteilung' => $ausgabe->aufgaben->filter->istOffen()->whereNull('verantwortlich_id')->count(),
        ]);
    }

    public function update(Request $request, Ausgabe $ausgabe)
    {
        Gate::authorize('ausgaben-verwalten');
        $daten = $request->validate([
            'titel' => ['sometimes', 'string', 'max:200'],
            'saison' => ['sometimes', 'string', 'max:16'],
            'halle_id' => ['nullable', 'exists:hallen,id'],
            'datum_von' => ['sometimes', 'date'],
            'datum_bis' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(array_keys(Ausgabe::STATUS))],
            'notizen' => ['nullable', 'string', 'max:10000'],
            'faelligkeiten_verschieben' => ['sometimes', 'boolean'],
        ]);
        // Ende nie vor dem Beginn (auch wenn nur eines der beiden Daten geaendert wird)
        $von = \Carbon\Carbon::parse($daten['datum_von'] ?? $ausgabe->datum_von);
        $bis = array_key_exists('datum_bis', $daten) ? ($daten['datum_bis'] ? \Carbon\Carbon::parse($daten['datum_bis']) : null) : $ausgabe->datum_bis;
        if ($bis && $bis->lt($von)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['datum_bis' => 'Der letzte Turniertag kann nicht vor dem ersten liegen.']);
        }
        $warRueckblick = $ausgabe->status === 'rueckblick';

        $altesDatum = $ausgabe->datum_von->copy();
        $altesEnde = $ausgabe->datum_bis?->copy();
        $ausgabe->update(collect($daten)->except('faelligkeiten_verschieben')->all());
        $ausgabe->refresh();

        // Aufgaben mit Abstand zum Turnier wandern immer mit
        if (! $altesDatum->isSameDay($ausgabe->datum_von) || $altesEnde?->toDateString() !== $ausgabe->datum_bis?->toDateString()) {
            foreach ($ausgabe->aufgaben()->offen()->whereNotNull('tage_vorher')->get() as $a) {
                $a->setRelation('ausgabe', $ausgabe);
                $a->rechneFaelligkeit();
                $a->save();
            }
        }
        // Aufgaben mit fixem Datum nur auf Wunsch
        if (($daten['faelligkeiten_verschieben'] ?? false) && ! $altesDatum->isSameDay($ausgabe->datum_von)) {
            $differenz = (int) $altesDatum->diffInDays($ausgabe->datum_von, false);
            foreach ($ausgabe->aufgaben()->offen()->whereNull('tage_vorher')->whereNotNull('faellig_am')->get() as $a) {
                $a->update(['faellig_am' => $a->faellig_am->addDays($differenz)]);
            }
        }

        if (isset($daten['status']) && $daten['status'] === 'rueckblick' && ! $warRueckblick) {
            $this->benachrichtiger->sende(
                User::where('aktiv', true)->get(), 'ausgabe', 'Rückblick: '.$ausgabe->titel,
                'Das Turnier ist vorbei. Was lief gut, was machen wir nächstes Mal anders?',
                route('ausgaben.rueckblick', $ausgabe), $request->user()
            );
        }

        Aktivitaet::protokolliere('ausgabe.geaendert', 'Turnier geändert: '.$ausgabe->titel, $ausgabe);

        return back()->with('erfolg', 'Turnier gespeichert.');
    }

    public function rueckblick(Request $request, Ausgabe $ausgabe)
    {
        $ausgabe->load('turnier');
        $ressortId = $request->integer('ressort') ?: null;

        $aufgaben = $ausgabe->aufgaben()->with(['ressort', 'verantwortlich', 'vorlage', 'erkenntnisse.user'])
            ->when($ressortId, fn ($q) => $q->where('ressort_id', $ressortId))
            ->orderBy('ressort_id')->orderBy('faellig_am')->get();

        $erkenntnisse = Erkenntnis::with(['user', 'vorlage', 'aufgabe', 'ressort'])
            ->where('ausgabe_id', $ausgabe->id)
            ->when($ressortId, fn ($q) => $q->where('ressort_id', $ressortId))
            ->latest()->get();

        $aufwand = $aufgaben->groupBy('ressort_id')->map(fn ($g) => [
            'ressort' => $g->first()->ressort->name,
            'geplant' => round($g->sum('aufwand_geplant'), 1),
            'effektiv' => round($g->sum('aufwand_effektiv'), 1),
        ])->values();

        return view('ausgaben.rueckblick', [
            'ausgabe' => $ausgabe,
            'aufgaben' => $aufgaben->groupBy('ressort_id'),
            'erkenntnisse' => $erkenntnisse,
            'ressorts' => Ressort::orderBy('sortierung')->get(),
            'ressortId' => $ressortId,
            'aufwand' => $aufwand,
            'ohneVorlage' => $aufgaben->whereNull('vorlage_id')->where('status', 'erledigt'),
        ]);
    }

    public function aufwand(Request $request, Ausgabe $ausgabe)
    {
        Gate::authorize('schreiben');
        $daten = $request->validate(['aufwand' => ['array'], 'aufwand.*' => ['nullable', 'numeric', 'min:0', 'max:999']]);
        $anzahl = 0;
        foreach ($daten['aufwand'] ?? [] as $id => $wert) {
            $aufgabe = $ausgabe->aufgaben()->find($id);
            if ($aufgabe && Gate::allows('aufgabe-bearbeiten', $aufgabe) && $wert !== null) {
                $aufgabe->update(['aufwand_effektiv' => $wert]);
                $anzahl++;
            }
        }

        return back()->with('erfolg', $anzahl.' Aufwände gespeichert.');
    }

    public function destroy(Ausgabe $ausgabe)
    {
        Gate::authorize('admin');
        Aktivitaet::protokolliere('ausgabe.geloescht', 'Turnier gelöscht: '.$ausgabe->titel);
        // Links haengen polymorph am Turnier und an seinen Aufgaben (keine Datenbank-Kaskade)
        \App\Models\Verknuepfung::where(fn ($q) => $q->where('bezug_type', 'ausgabe')->where('bezug_id', $ausgabe->id))
            ->orWhere(fn ($q) => $q->where('bezug_type', 'aufgabe')->whereIn('bezug_id', $ausgabe->aufgaben()->pluck('id')))->delete();
        $ausgabe->delete();

        return redirect()->route('ausgaben.index')->with('erfolg', 'Turnier gelöscht.');
    }
}
