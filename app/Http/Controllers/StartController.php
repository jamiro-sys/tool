<?php

namespace App\Http\Controllers;

use App\Models\Aktivitaet;
use App\Models\Ausgabe;
use App\Models\Aufgabe;
use App\Models\Ressort;
use Illuminate\Http\Request;

class StartController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $naechste = Ausgabe::with('turnier', 'halle')->whereIn('status', ['planung', 'laufend', 'rueckblick'])->orderBy('datum_von')->get();
        $ausgabe = $naechste->first();

        $meine = Aufgabe::with(['ressort', 'ausgabe'])
            ->whereHas('ausgabe', fn ($q) => $q->whereIn('status', ['planung', 'laufend', 'rueckblick']))
            ->where(fn ($q) => $q->where('verantwortlich_id', $user->id)->orWhere('begleitung_id', $user->id))
            ->offen()
            ->orderByRaw('faellig_am is null')
            ->orderBy('faellig_am')
            ->get();

        $gruppen = [
            'ueberfaellig' => $meine->filter->istUeberfaellig(),
            'woche' => $meine->filter(fn ($a) => ! $a->istUeberfaellig() && $a->faellig_am && $a->faellig_am->lte(today()->addDays(7))),
            'spaeter' => $meine->filter(fn ($a) => ! $a->faellig_am || $a->faellig_am->gt(today()->addDays(7))),
        ];

        $erledigtWoche = Aufgabe::where('erledigt_von', $user->id)->where('erledigt_am', '>=', now()->subDays(7))->count();

        $daten = compact('naechste', 'ausgabe', 'gruppen', 'erledigtWoche', 'meine');

        if ($user->istExperte()) {
            $daten['ressorts'] = $ausgabe ? $this->ressortFortschritt($ausgabe) : collect();
            $daten['aktivitaeten'] = Aktivitaet::with('user')->where('aktion', '!=', 'anmeldung')->latest()->limit(8)->get();
            $daten['ohneZuteilung'] = $ausgabe ? $ausgabe->aufgaben()->offen()->whereNull('verantwortlich_id')->count() : 0;
            $daten['ueberfaelligAlle'] = $ausgabe ? $ausgabe->aufgaben()->offen()->whereDate('faellig_am', '<', today())->count() : 0;
        }

        return view('start', $daten);
    }

    public static function ressortFortschritt(Ausgabe $ausgabe)
    {
        $aufgaben = $ausgabe->aufgaben()->get(['id', 'ressort_id', 'status', 'faellig_am']);

        return Ressort::orderBy('sortierung')->get()->map(function ($r) use ($aufgaben) {
            $eigene = $aufgaben->where('ressort_id', $r->id)->where('status', '!=', 'entfaellt');
            $total = $eigene->count();
            $erledigt = $eigene->where('status', 'erledigt')->count();

            return [
                'ressort' => $r,
                'total' => $total,
                'erledigt' => $erledigt,
                'ueberfaellig' => $eigene->filter(fn ($a) => in_array($a->status, ['offen', 'in_arbeit']) && $a->faellig_am && $a->faellig_am->lt(today()))->count(),
                'prozent' => $total ? (int) round($erledigt / $total * 100) : 0,
            ];
        })->filter(fn ($e) => $e['total'] > 0)->values();
    }
}
