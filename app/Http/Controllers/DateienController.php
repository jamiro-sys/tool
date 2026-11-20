<?php

namespace App\Http\Controllers;

use App\Models\Aktivitaet;
use App\Models\Dokument;
use App\Models\Ordner;
use App\Models\Ressort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Gemeinsame Dateiablage: Dateien und Links in Ordnern je Ressort (mit eigenen Unterordnern). */
class DateienController extends Controller
{
    public function index(Request $request)
    {
        $filter = [
            'ressort' => $request->query('ressort'),
            'art' => in_array($request->query('art'), ['datei', 'link'], true) ? $request->query('art') : null,
            'q' => trim((string) $request->query('q')),
        ];

        $dokumente = Dokument::with(['ressort', 'user', 'ordner'])
            ->when($filter['ressort'] === 'allgemein', fn ($q) => $q->whereNull('ressort_id'))
            ->when($filter['ressort'] && $filter['ressort'] !== 'allgemein', fn ($q) => $q->where('ressort_id', (int) $filter['ressort']))
            ->when($filter['art'], fn ($q) => $q->where('typ', $filter['art']))
            ->when($filter['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('titel', 'like', '%'.$filter['q'].'%')->orWhere('original_name', 'like', '%'.$filter['q'].'%')))
            ->orderBy('titel')->get();

        $ressorts = Ressort::orderBy('sortierung')->orderBy('name')->get();
        $ordner = Ordner::orderBy('sortierung')->orderBy('name')->get();

        return view('dateien.index', [
            'dokumente' => $dokumente,
            'filter' => $filter,
            'ressorts' => $ressorts,
            'ordner' => $ordner,
            'flach' => $filter['q'] !== '' || $filter['art'] !== null,
            'gesamt' => Dokument::count(),
        ]);
    }

    public function speichern(Request $request)
    {
        Gate::authorize('schreiben');
        $daten = $request->validate([
            'ressort_id' => ['nullable', 'exists:ressorts,id'],
            'ordner_id' => ['nullable', 'string'],
            'neuer_ordner' => ['nullable', 'string', 'max:120', 'required_if:ordner_id,neu'],
            'titel' => ['nullable', 'string', 'max:200'],
            'url' => ['nullable', 'url:http,https', 'max:1024', 'required_without:dateien'],
            'dateien' => ['nullable', 'array', 'max:20', 'required_without:url'],
            'dateien.*' => ['file', 'max:51200'],
        ], [
            'dateien.*.max' => 'Eine Datei ist grösser als 50 MB.',
            'url.required_without' => 'Bitte eine Datei wählen oder einen Link angeben.',
            'dateien.required_without' => 'Bitte eine Datei wählen oder einen Link angeben.',
            'neuer_ordner.required_if' => 'Bitte einen Namen für den neuen Ordner angeben.',
        ]);

        $ressortId = isset($daten['ressort_id']) ? (int) $daten['ressort_id'] : null;
        $ordner = $this->ordnerFuer($ressortId, $daten['ordner_id'] ?? null, $daten['neuer_ordner'] ?? null);
        $basis = ['ressort_id' => $ressortId, 'ordner_id' => $ordner?->id, 'user_id' => $request->user()->id];
        $anzahl = 0;
        foreach ($request->file('dateien', []) as $datei) {
            $name = $datei->getClientOriginalName();
            Dokument::create($basis + [
                'titel' => count($request->file('dateien')) === 1 && filled($daten['titel'] ?? null) ? $daten['titel'] : \App\Support\Dateiname::titel($name),
                'typ' => 'datei',
                'pfad' => $datei->store('dokumente/'.($ressortId ?? 'allgemein'), 'local'),
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
        $ort = $ordner ? $ordner->pfad() : (Ressort::find($ressortId)?->name ?? 'Allgemein');
        Aktivitaet::protokolliere('dateien.neu', $anzahl.' '.($anzahl === 1 ? 'Eintrag' : 'Einträge').' abgelegt in '.$ort);

        return back()->with('erfolg', ($anzahl === 1 ? 'Abgelegt' : $anzahl.' Einträge abgelegt').' in '.$ort.'.');
    }

    /** Titel aendern und an einen anderen Ort verschieben (Ressort oder Unterordner). */
    public function aendern(Request $request, Dokument $dokument)
    {
        Gate::authorize('schreiben');
        $daten = $request->validate([
            'titel' => ['required', 'string', 'max:200'],
            'ort' => ['nullable', 'string', 'regex:/^(r|o):\d+$/'],
            'ressort_id' => ['nullable', 'exists:ressorts,id'],
        ]);
        [$ressortId, $ordnerId] = $this->ortAufloesen($daten['ort'] ?? null, $daten['ressort_id'] ?? null);
        $dokument->update(['titel' => $daten['titel'], 'ressort_id' => $ressortId, 'ordner_id' => $ordnerId]);

        return back()->with('erfolg', 'Gespeichert.');
    }

    public function ordnerSpeichern(Request $request)
    {
        Gate::authorize('schreiben');
        $daten = $request->validate([
            'ressort_id' => ['nullable', 'exists:ressorts,id'],
            'name' => ['required', 'string', 'max:120'],
        ]);
        $ordner = $this->ordnerFuer(isset($daten['ressort_id']) ? (int) $daten['ressort_id'] : null, 'neu', $daten['name']);

        return back()->with('erfolg', 'Ordner «'.$ordner->pfad().'» ist bereit.');
    }

    public function ordnerAendern(Request $request, Ordner $ordner)
    {
        Gate::authorize('schreiben');
        $daten = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('ordner')->where('ressort_id', $ordner->ressort_id)->ignore($ordner->id)]],
            ['name.unique' => 'Diesen Ordner gibt es hier schon.']);
        $ordner->update(['name' => trim($daten['name'])]);

        return back()->with('erfolg', 'Ordner umbenannt.');
    }

    public function ordnerLoeschen(Ordner $ordner)
    {
        Gate::authorize('schreiben');
        $anzahl = $ordner->dokumente()->update(['ordner_id' => null]);
        $ort = $ordner->ressort?->name ?? 'Allgemein';
        $ordner->delete();

        return back()->with('erfolg', 'Ordner gelöscht.'.($anzahl ? ' '.$anzahl.' '.($anzahl === 1 ? 'Eintrag liegt' : 'Einträge liegen').' jetzt direkt in '.$ort.'.' : ''));
    }

    /** Bestehenden Unterordner pruefen oder neuen anlegen; muss zum Ressort passen. */
    protected function ordnerFuer(?int $ressortId, ?string $ordnerId, ?string $neuerName): ?Ordner
    {
        if ($ordnerId === 'neu' && filled($neuerName)) {
            return Ordner::firstOrCreate(['ressort_id' => $ressortId, 'name' => trim($neuerName)]);
        }
        if (! filled($ordnerId) || $ordnerId === 'neu') {
            return null;
        }
        $ordner = Ordner::find((int) $ordnerId);
        if (! $ordner || $ordner->ressort_id !== $ressortId) {
            throw ValidationException::withMessages(['ordner_id' => 'Dieser Ordner gehört zu einem anderen Ressort.']);
        }

        return $ordner;
    }

    /** "r:ID" = direkt im Ressort (0 = Allgemein), "o:ID" = Unterordner. */
    protected function ortAufloesen(?string $ort, ?string $ressortId): array
    {
        if (! $ort) {
            return [$ressortId ? (int) $ressortId : null, null];
        }
        [$art, $id] = explode(':', $ort);
        if ($art === 'o') {
            $ordner = Ordner::findOrFail((int) $id);

            return [$ordner->ressort_id, $ordner->id];
        }

        return [(int) $id ?: null, null];
    }
}
