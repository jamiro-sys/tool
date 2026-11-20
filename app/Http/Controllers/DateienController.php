<?php

namespace App\Http\Controllers;

use App\Models\Aktivitaet;
use App\Models\Dokument;
use App\Models\Ressort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Gemeinsame Dateiablage: Dateien und Links, optional einem Ressort zugeteilt. */
class DateienController extends Controller
{
    public function index(Request $request)
    {
        $filter = [
            'ressort' => $request->query('ressort'),
            'art' => in_array($request->query('art'), ['datei', 'link'], true) ? $request->query('art') : null,
            'q' => trim((string) $request->query('q')),
        ];

        $dokumente = Dokument::with(['ressort', 'user'])
            ->when($filter['ressort'] === 'allgemein', fn ($q) => $q->whereNull('ressort_id'))
            ->when($filter['ressort'] && $filter['ressort'] !== 'allgemein', fn ($q) => $q->where('ressort_id', (int) $filter['ressort']))
            ->when($filter['art'], fn ($q) => $q->where('typ', $filter['art']))
            ->when($filter['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('titel', 'like', '%'.$filter['q'].'%')->orWhere('original_name', 'like', '%'.$filter['q'].'%')))
            ->latest()->get();

        return view('dateien.index', [
            'dokumente' => $dokumente,
            'filter' => $filter,
            'ressorts' => Ressort::orderBy('sortierung')->orderBy('name')->get(),
            'gesamt' => Dokument::count(),
        ]);
    }

    public function speichern(Request $request)
    {
        Gate::authorize('schreiben');
        $daten = $request->validate([
            'ressort_id' => ['nullable', 'exists:ressorts,id'],
            'titel' => ['nullable', 'string', 'max:200'],
            'url' => ['nullable', 'url:http,https', 'max:1024', 'required_without:dateien'],
            'dateien' => ['nullable', 'array', 'max:20', 'required_without:url'],
            'dateien.*' => ['file', 'max:51200'],
        ], ['dateien.*.max' => 'Eine Datei ist grösser als 50 MB.', 'url.required_without' => 'Bitte eine Datei wählen oder einen Link angeben.', 'dateien.required_without' => 'Bitte eine Datei wählen oder einen Link angeben.']);

        $basis = ['ressort_id' => $daten['ressort_id'] ?? null, 'user_id' => $request->user()->id];
        $anzahl = 0;
        foreach ($request->file('dateien', []) as $datei) {
            $name = $datei->getClientOriginalName();
            Dokument::create($basis + [
                'titel' => count($request->file('dateien')) === 1 && filled($daten['titel'] ?? null) ? $daten['titel'] : pathinfo($name, PATHINFO_FILENAME),
                'typ' => 'datei',
                'pfad' => $datei->store('dokumente/'.($basis['ressort_id'] ?? 'allgemein'), 'local'),
                'original_name' => $name,
                'groesse' => $datei->getSize(),
                'mime' => $datei->getMimeType(),
            ]);
            $anzahl++;
        }
        if (filled($daten['url'] ?? null)) {
            Dokument::create($basis + ['titel' => $daten['titel'] ?: (string) parse_url($daten['url'], PHP_URL_HOST), 'typ' => 'link', 'url' => $daten['url']]);
            $anzahl++;
        }
        Aktivitaet::protokolliere('dateien.neu', $anzahl.' '.($anzahl === 1 ? 'Eintrag' : 'Einträge').' in der Dateiablage abgelegt');

        return back()->with('erfolg', $anzahl === 1 ? 'Abgelegt.' : $anzahl.' Einträge abgelegt.');
    }

    public function aendern(Request $request, Dokument $dokument)
    {
        Gate::authorize('schreiben');
        $daten = $request->validate([
            'titel' => ['required', 'string', 'max:200'],
            'ressort_id' => ['nullable', 'exists:ressorts,id'],
        ]);
        $dokument->update($daten);

        return back()->with('erfolg', 'Gespeichert.');
    }
}
