<?php

namespace App\Http\Controllers;

use App\Models\Aktivitaet;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\Vorlage;
use App\Services\AusgabePlaner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class VorlageController extends Controller
{
    public function __construct(protected AusgabePlaner $planer) {}

    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['ja', 'nein'], true) ? $request->query('status') : 'alle';
        $vorlagen = Vorlage::with(['ressort', 'turnier'])->withCount(['erkenntnisse as offene_erkenntnisse' => fn ($q) => $q->whereNull('umgesetzt_am')])
            ->when($request->filled('ressort'), fn ($q) => $q->where('ressort_id', $request->integer('ressort')))
            ->when($request->filled('turnier'), fn ($q) => $q->where(fn ($w) => $w->whereNull('turnier_id')->orWhere('turnier_id', $request->integer('turnier'))))
            ->when($status === 'ja', fn ($q) => $q->where('aktiv', true))
            ->when($status === 'nein', fn ($q) => $q->where('aktiv', false))
            ->when($request->query('nur') === 'ohne_anleitung', fn ($q) => $q->where(fn ($w) => $w->whereNull('anleitung')->orWhere('anleitung', '')))
            ->when($request->filled('q'), fn ($q) => $q->where('titel', 'like', '%'.$request->query('q').'%'))
            ->orderByDesc('aktiv')->orderByDesc('tage_vorher')->orderBy('titel')->get();

        $ressorts = Ressort::orderBy('sortierung')->orderBy('name')->get();
        $gefiltert = array_filter($request->only(['ressort', 'turnier', 'q', 'nur'])) !== [] || $status !== 'alle';

        return view('vorlagen.index', [
            'vorlagen' => $vorlagen->groupBy('ressort_id'),
            'anzahl' => Vorlage::where('aktiv', true)->count(),
            'ressorts' => $ressorts,
            'sichtbar' => $gefiltert ? $ressorts->filter(fn ($r) => $vorlagen->contains('ressort_id', $r->id)) : $ressorts,
            'verwaltbar' => $this->verwaltbareRessorts(),
            'turniere' => Turnier::all(),
            'filter' => $request->only(['ressort', 'turnier', 'q', 'nur']) + ['status' => $status],
            'gefiltert' => $gefiltert,
        ]);
    }

    public function create(Request $request)
    {
        return view('vorlagen.form', [
            'vorlage' => new Vorlage(['ressort_id' => $request->integer('ressort') ?: null, 'tage_vorher' => 14, 'prioritaet' => 'normal']),
            'ressorts' => $this->verwaltbareRessorts(),
            'turniere' => Turnier::all(),
        ]);
    }

    public function store(Request $request)
    {
        $daten = $this->validiere($request);
        Gate::authorize('ressort-verwalten', Ressort::findOrFail($daten['ressort_id']));
        $vorlage = Vorlage::create($daten + ['aktiv' => true]);
        Aktivitaet::protokolliere('vorlage.erstellt', 'Im Jahresplan: '.$vorlage->titel, $vorlage);

        if ($request->boolean('schnell')) {
            return redirect()->route('vorlagen.index', array_filter($request->only(['ressort', 'status'])))
                ->with('erfolg', '«'.$vorlage->titel.'» ist im Jahresplan.')
                ->with('schnell', ['ressort_id' => $vorlage->ressort_id, 'tage_vorher' => $vorlage->tage_vorher, 'neu' => $vorlage->id]);
        }

        return redirect()->route('vorlagen.show', $vorlage)->with('erfolg', 'Im Jahresplan gespeichert.');
    }

    public function show(Vorlage $vorlage)
    {
        $vorlage->load(['ressort', 'turnier', 'versionen.user', 'erkenntnisse.user', 'erkenntnisse.ausgabe', 'aufgaben.ausgabe', 'aufgaben.verantwortlich']);

        return view('vorlagen.show', ['vorlage' => $vorlage]);
    }

    public function edit(Vorlage $vorlage)
    {
        Gate::authorize('ressort-verwalten', $vorlage->ressort);

        return view('vorlagen.form', ['vorlage' => $vorlage, 'ressorts' => $this->verwaltbareRessorts(), 'turniere' => Turnier::all()]);
    }

    public function update(Request $request, Vorlage $vorlage)
    {
        Gate::authorize('ressort-verwalten', $vorlage->ressort);
        $daten = $this->validiere($request);
        $this->planer->aendereVorlage($vorlage, $daten, $request->input('notiz'));
        Aktivitaet::protokolliere('vorlage.geaendert', 'Jahresplan geändert: '.$vorlage->titel, $vorlage);

        return redirect()->route('vorlagen.show', $vorlage)->with('erfolg', 'Gespeichert.');
    }

    public function aktiv(Request $request, Vorlage $vorlage)
    {
        Gate::authorize('ressort-verwalten', $vorlage->ressort);
        $aktiv = $request->boolean('aktiv');
        $this->planer->aendereVorlage($vorlage, [
            'aktiv' => $aktiv,
            'deaktiviert_am' => $aktiv ? null : now(),
            'deaktiviert_grund' => $aktiv ? null : $request->input('grund'),
        ], $aktiv ? 'Wieder im Jahresplan' : 'Aus dem Jahresplan genommen');
        if ($request->wantsJson()) {
            return response()->json(['wert' => $aktiv]);
        }

        return back()->with('erfolg', $aktiv ? 'Kommt wieder bei jedem neuen Turnier.' : 'Kommt nicht mehr bei neuen Turnieren. Verlauf und frühere Aufgaben bleiben erhalten.');
    }

    protected function verwaltbareRessorts()
    {
        $user = auth()->user();

        return Ressort::orderBy('sortierung')->get()->filter(fn ($r) => $user->istAdmin() || $user->leitetRessort($r));
    }

    protected function validiere(Request $request): array
    {
        $daten = $request->validate([
            'ressort_id' => ['required', 'exists:ressorts,id'],
            'turnier_id' => ['nullable', 'exists:turniere,id'],
            'titel' => ['required', 'string', 'max:200'],
            'beschreibung' => ['nullable', 'string', 'max:10000'],
            'anleitung' => ['nullable', 'string', 'max:20000'],
            'tage_vorher' => ['required', 'integer', 'min:-120', 'max:730'],
            'aufwand_std' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'prioritaet' => ['required', Rule::in(array_keys(Vorlage::PRIORITAETEN))],
            'checkliste_text' => ['nullable', 'string', 'max:5000'],
            'aktiv_seit' => ['nullable', 'string', 'max:16'],
        ]);
        $daten['checkliste'] = collect(preg_split('/\r?\n/', $daten['checkliste_text'] ?? ''))->map(fn ($z) => trim($z))->filter()->values()->all() ?: null;
        unset($daten['checkliste_text']);

        return $daten;
    }
}
