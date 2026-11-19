<?php

namespace App\Http\Controllers;

use App\Models\Ausgabe;
use App\Models\Aufgabe;
use App\Models\Erkenntnis;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\User;

class AuswertungController extends Controller
{
    public function index()
    {
        $ausgaben = Ausgabe::with('turnier')->orderBy('datum_von')->get()->take(-8)->values();
        $ressorts = Ressort::orderBy('sortierung')->get();
        $aufgaben = Aufgabe::whereIn('ausgabe_id', $ausgaben->pluck('id'))->get();

        // Aufwand pro Ressort ueber die Ausgaben (effektiv, sonst geplant)
        $aufwand = [
            'labels' => $ausgaben->map(fn ($a) => $a->turnier->kurz.' '.$a->saison)->all(),
            'reihen' => $ressorts->map(fn ($r) => [
                'name' => $r->name,
                'werte' => $ausgaben->map(fn ($a) => round($aufgaben->where('ausgabe_id', $a->id)->where('ressort_id', $r->id)->sum(fn ($x) => $x->aufwand_effektiv ?? $x->aufwand_geplant ?? 0), 1))->all(),
            ])->filter(fn ($r) => array_sum($r['werte']) > 0)->values()->all(),
        ];

        // Vergleich der beiden Turniere (letzte Ausgabe je Turnier)
        $vergleich = Turnier::all()->map(function ($t) use ($ressorts) {
            $letzte = Ausgabe::where('turnier_id', $t->id)->orderByDesc('datum_von')->first();
            $a = $letzte ? Aufgabe::where('ausgabe_id', $letzte->id)->get() : collect();

            return [
                'name' => $t->name,
                'ausgabe' => $letzte?->titel,
                'werte' => $ressorts->map(fn ($r) => $a->where('ressort_id', $r->id)->where('status', '!=', 'entfaellt')->count())->all(),
            ];
        })->values()->all();

        // Puenktlichkeit: erledigt bis Faelligkeit
        $puenktlich = $ausgaben->map(function ($a) use ($aufgaben) {
            $erledigt = $aufgaben->where('ausgabe_id', $a->id)->where('status', 'erledigt')->filter(fn ($x) => $x->faellig_am);
            $rechtzeitig = $erledigt->filter(fn ($x) => $x->erledigt_am && $x->erledigt_am->lte($x->faellig_am->copy()->endOfDay()))->count();

            return $erledigt->count() ? (int) round($rechtzeitig / $erledigt->count() * 100) : null;
        })->all();

        // Verteilung der Arbeit auf Personen in der naechsten Ausgabe
        $naechste = Ausgabe::naechste();
        $last = $naechste ? User::where('aktiv', true)->get()->map(fn ($u) => [
            'name' => $u->name,
            'offen' => $naechste->aufgaben()->offen()->where('verantwortlich_id', $u->id)->count(),
            'erledigt' => $naechste->aufgaben()->where('status', 'erledigt')->where('verantwortlich_id', $u->id)->count(),
        ])->filter(fn ($e) => $e['offen'] + $e['erledigt'] > 0)->sortByDesc('offen')->values()->all() : [];

        $wandel = [
            'neu' => Erkenntnis::where('vorschlag', 'neu')->whereNotNull('umgesetzt_am')->count(),
            'angepasst' => Erkenntnis::where('vorschlag', 'anpassen')->whereNotNull('umgesetzt_am')->count(),
            'gestrichen' => Erkenntnis::where('vorschlag', 'streichen')->whereNotNull('umgesetzt_am')->count(),
            'offen' => Erkenntnis::whereNull('umgesetzt_am')->count(),
        ];

        return view('auswertung', [
            'aufwand' => $aufwand,
            'vergleich' => $vergleich,
            'ressortNamen' => $ressorts->pluck('name')->all(),
            'puenktlich' => ['labels' => $aufwand['labels'], 'werte' => $puenktlich],
            'last' => $last,
            'naechste' => $naechste,
            'wandel' => $wandel,
            'wissen' => Ressort::with(['mitglieder', 'vorlagen', 'kontakte', 'wissen'])->orderBy('sortierung')->get()->map(fn ($r) => ['ressort' => $r, 'stand' => $r->wissensstand()]),
        ]);
    }
}
