<?php

namespace App\Http\Controllers;

use App\Models\Aktivitaet;
use App\Models\Ausgabe;
use App\Models\Einstellung;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\Vorlage;
use App\Services\AufgabenAssistent;
use App\Services\AusgabePlaner;
use App\Support\Wann;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/** Aufgaben mit Claude erfassen: Text/Dateien -> Vorschlaege pruefen -> uebernehmen. */
class AssistentController extends Controller
{
    protected function assistent(): AufgabenAssistent
    {
        return app(AufgabenAssistent::class);
    }

    public function vorschlag(Request $request)
    {
        $daten = $request->validate([
            'art' => ['required', Rule::in(['jahresplan', 'ressort'])],
            'ressort_id' => ['nullable', 'required_if:art,ressort', 'exists:ressorts,id'],
            'text' => ['nullable', 'string', 'max:30000', 'required_without:dateien'],
            'dateien' => ['nullable', 'array', 'max:5'],
            'dateien.*' => ['file', 'max:15360', 'mimetypes:'.implode(',', AufgabenAssistent::DATEITYPEN)],
        ], [
            'text.required_without' => 'Bitte Text eingeben oder eine Datei anhängen.',
            'dateien.*.mimetypes' => 'Claude liest PDF und Bilder (JPG, PNG, WEBP).',
            'dateien.*.max' => 'Eine Datei ist grösser als 15 MB.',
        ]);
        [$ressort, $ausgabe, $zurueck] = $this->kontext($daten['art'], $daten['ressort_id'] ?? null);
        abort_unless($this->assistent()->bereit(), 503, 'Der Claude-Assistent ist noch nicht eingerichtet.');

        $ressorts = $daten['art'] === 'jahresplan' ? $this->verwaltbar() : Ressort::orderBy('sortierung')->get();
        $turniere = Turnier::orderBy('name')->get();
        @set_time_limit(180);
        try {
            $ergebnis = $this->assistent()->vorschlaege((string) ($daten['text'] ?? ''), $request->file('dateien', []), [
                'art' => $daten['art'], 'ressort' => $ressort, 'turnier' => $ausgabe?->titel, 'ressorts' => $ressorts, 'turniere' => $turniere,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Claude-Assistent: '.$e->getMessage());

            return redirect($zurueck)->withInput()->withErrors(['assistent' => $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Claude ist gerade nicht erreichbar. Bitte in einer Minute nochmals versuchen.']);
        }
        Aktivitaet::protokolliere('assistent.vorschlag', count($ergebnis['aufgaben']).' Vorschläge von Claude ('.($ressort?->name ?? 'Jahresplan').')');

        if (! $ergebnis['aufgaben']) {
            return redirect($zurueck)->withInput()->withErrors(['assistent' => 'Claude hat keine Aufgaben gefunden.'.($ergebnis['hinweis'] ? ' '.$ergebnis['hinweis'] : '')]);
        }

        return view('assistent.vorschlag', [
            'art' => $daten['art'],
            'ressort' => $ressort,
            'ausgabe' => $ausgabe,
            'zurueck' => $zurueck,
            'vorschlaege' => $ergebnis['aufgaben'],
            'hinweis' => $ergebnis['hinweis'],
            'ressorts' => $ressorts,
            'turniere' => $turniere,
            'darfPlan' => $ressort ? Gate::allows('ressort-verwalten', $ressort) : true,
        ]);
    }

    public function uebernehmen(Request $request)
    {
        $daten = $request->validate([
            'art' => ['required', Rule::in(['jahresplan', 'ressort'])],
            'ressort_id' => ['nullable', 'required_if:art,ressort', 'exists:ressorts,id'],
            'zeilen' => ['required', 'array', 'max:100'],
            'zeilen.*.nehmen' => ['nullable', 'boolean'],
            'zeilen.*.titel' => ['nullable', 'string', 'max:200'],
            'zeilen.*.beschreibung' => ['nullable', 'string', 'max:10000'],
            'zeilen.*.ressort_id' => ['nullable', 'exists:ressorts,id'],
            'zeilen.*.tage_vorher' => ['nullable', 'integer', 'min:-365', 'max:730'],
            'zeilen.*.bezug' => ['nullable', Rule::in(Wann::BEZUG)],
            'zeilen.*.aufwand' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'zeilen.*.prioritaet' => ['nullable', Rule::in(['tief', 'normal', 'hoch'])],
            'zeilen.*.jedes_jahr' => ['nullable', 'boolean'],
            'zeilen.*.turniere' => ['nullable', 'array'],
            'zeilen.*.turniere.*' => ['integer', 'exists:turniere,id'],
            'zeilen.*.checkliste' => ['nullable', 'string', 'max:5000'],
        ]);
        [$ressort, $ausgabe, $zurueck] = $this->kontext($daten['art'], $daten['ressort_id'] ?? null);
        $verwaltbar = $this->verwaltbar()->pluck('id');
        $anzahl = 0;
        $imPlan = 0;

        foreach ($daten['zeilen'] as $z) {
            if (empty($z['nehmen']) || blank($z['titel'] ?? null)) {
                continue;
            }
            $checkliste = collect(preg_split('/\r?\n/', (string) ($z['checkliste'] ?? '')))->map(fn ($p) => trim($p))->filter()->values();
            $tage = isset($z['tage_vorher']) && $z['tage_vorher'] !== '' ? (int) $z['tage_vorher'] : null;
            $bezug = $z['bezug'] ?? 'beginn';

            if ($daten['art'] === 'jahresplan') {
                $rid = (int) ($z['ressort_id'] ?? 0);
                if (! $verwaltbar->contains($rid)) {
                    continue;
                }
                $vorlage = Vorlage::create([
                    'ressort_id' => $rid, 'titel' => $z['titel'], 'beschreibung' => $z['beschreibung'] ?? null,
                    'tage_vorher' => $tage ?? 14, 'bezug' => $tage === null ? 'beginn' : $bezug,
                    'aufwand_std' => $z['aufwand'] ?? null, 'prioritaet' => $z['prioritaet'] ?? 'normal',
                    'checkliste' => $checkliste->all() ?: null, 'aktiv' => true,
                ]);
                $vorlage->setzeTurniere($z['turniere'] ?? []);
                $anzahl++;

                continue;
            }

            $aufgabe = $ausgabe->aufgaben()->create([
                'ressort_id' => $ressort->id, 'titel' => $z['titel'], 'beschreibung' => $z['beschreibung'] ?? null,
                'tage_vorher' => $tage, 'bezug' => $bezug,
                'faellig_am' => $tage !== null ? Wann::fuer($ausgabe, $tage, $bezug) : null,
                'aufwand_geplant' => $z['aufwand'] ?? null, 'prioritaet' => $z['prioritaet'] ?? 'normal',
                'checkliste' => $checkliste->map(fn ($p) => ['text' => $p, 'erledigt' => false])->all() ?: null,
                'status' => 'offen',
            ]);
            $anzahl++;
            if (! empty($z['jedes_jahr']) && Gate::allows('ressort-verwalten', $ressort)) {
                app(AusgabePlaner::class)->inJahresplan($aufgabe, $z['turniere'] ?? []);
                $imPlan++;
            }
        }

        Aktivitaet::protokolliere('assistent.uebernommen', $anzahl.' Aufgaben mit Claude erfasst ('.($ressort?->name ?? 'Jahresplan').')');
        $meldung = $daten['art'] === 'jahresplan'
            ? ($anzahl === 1 ? 'Eine Aufgabe ist im Jahresplan.' : $anzahl.' Aufgaben sind im Jahresplan.')
            : ($anzahl === 1 ? 'Eine Aufgabe erstellt.' : $anzahl.' Aufgaben erstellt.').($imPlan ? ' '.$imPlan.' davon kommen jedes Jahr.' : '');

        return redirect($zurueck)->with('erfolg', $anzahl ? $meldung : 'Nichts übernommen.');
    }

    /** Schluessel unter Admin > System speichern (verschluesselt) oder entfernen. */
    public function schluessel(Request $request)
    {
        Gate::authorize('admin');
        $daten = $request->validate(['schluessel' => ['nullable', 'string', 'max:300'], 'entfernen' => ['nullable', 'boolean']]);
        if ($request->boolean('entfernen')) {
            Einstellung::setze('claude_schluessel', null);
            Aktivitaet::protokolliere('assistent.schluessel', 'Claude-Schlüssel entfernt');

            return back()->with('erfolg', 'Claude-Schlüssel entfernt.');
        }
        $wert = trim((string) ($daten['schluessel'] ?? ''));
        if (! str_starts_with($wert, 'sk-ant-')) {
            return back()->withErrors(['schluessel' => 'Das sieht nicht nach einem Anthropic-Schlüssel aus (beginnt mit sk-ant-).']);
        }
        Einstellung::setze('claude_schluessel', Crypt::encryptString($wert));
        Aktivitaet::protokolliere('assistent.schluessel', 'Claude-Schlüssel gespeichert');

        return back()->with('erfolg', 'Claude-Schlüssel gespeichert. Der Assistent ist bereit.');
    }

    /** @return array{0: ?Ressort, 1: ?Ausgabe, 2: string} */
    protected function kontext(string $art, $ressortId): array
    {
        if ($art === 'jahresplan') {
            abort_if($this->verwaltbar()->isEmpty(), 403);

            return [null, null, route('vorlagen.index')];
        }
        $ressort = Ressort::findOrFail($ressortId);
        abort_unless(Gate::allows('ressort-mitarbeiten', $ressort) || Gate::allows('ressort-verwalten', $ressort), 403);
        $ausgabe = Ausgabe::naechste();
        abort_unless($ausgabe, 422, 'Es ist noch kein Turnier geplant.');

        return [$ressort, $ausgabe, route('ressorts.show', [$ressort, 'tab' => 'aufgaben'])];
    }

    protected function verwaltbar()
    {
        $u = auth()->user();

        return Ressort::orderBy('sortierung')->orderBy('name')->get()->filter(fn ($r) => $u->istAdmin() || $u->leitetRessort($r))->values();
    }
}
