<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aktivitaet;
use App\Models\Ausgabe;
use App\Models\Ressort;
use App\Models\Turnier;
use App\Models\Vorlage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatenController extends Controller
{
    public function index()
    {
        return view('admin.daten', ['ausgaben' => Ausgabe::with('turnier')->orderByDesc('datum_von')->get()]);
    }

    /** Neu beginnen: alle Inhalte leeren, Personen und Einstellungen behalten. */
    public function leeren(Request $request, \App\Services\Inhalte $inhalte)
    {
        $request->validate(['bestaetigung' => ['required', 'in:LEEREN']], ['bestaetigung.in' => 'Bitte genau LEEREN eintippen.']);

        try {
            $ergebnis = $inhalte->leeren();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('warnung', 'Leeren abgebrochen, es wurde nichts gelöscht. Grund: '.\Illuminate\Support\Str::limit($e->getMessage(), 200));
        }
        Aktivitaet::protokolliere('daten.leeren', 'Alle Inhalte geleert (Sicherung '.$ergebnis['sicherung'].')');

        return redirect()->route('admin.einstellungen')
            ->with('erfolg', 'Alle Inhalte sind geleert. Eine Sicherung liegt unter Admin > Sicherungen. Lege jetzt Hallen und Turnierarten an.');
    }

    /** Vorlagenpaket: alle Vorlagen mit Ressortnamen, uebertragbar in eine andere Installation. */
    public function vorlagenExport()
    {
        $paket = [
            'typ' => 'ok-tool-vorlagen',
            'version' => 1,
            'erstellt' => now()->toIso8601String(),
            'vorlagen' => Vorlage::with('ressort', 'turniere')->get()->map(fn ($v) => [
                'ressort' => $v->ressort->name,
                'ressort_kuerzel' => $v->ressort->kuerzel,
                'turniere' => $v->turniere->map(fn ($t) => $t->kurz ?: $t->name)->values()->all(),
                ...$v->only(['titel', 'beschreibung', 'anleitung', 'tage_vorher', 'bezug', 'haeufigkeit', 'aufwand_std', 'prioritaet', 'checkliste', 'aktiv']),
            ])->all(),
        ];

        return response()->streamDownload(fn () => print (json_encode($paket, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), 'vorlagen-'.now()->format('Y-m-d').'.json', ['Content-Type' => 'application/json']);
    }

    public function vorlagenImport(Request $request)
    {
        $request->validate(['datei' => ['required', 'file', 'max:5120']]);
        $paket = json_decode(file_get_contents($request->file('datei')->getRealPath()), true);
        if (($paket['typ'] ?? null) !== 'ok-tool-vorlagen') {
            return back()->withErrors(['datei' => 'Die Datei ist kein Jahresplan-Export.']);
        }

        $neu = 0;
        $ueber = 0;
        $unbekannt = [];
        DB::transaction(function () use ($paket, &$neu, &$ueber, &$unbekannt) {
            foreach ($paket['vorlagen'] as $v) {
                $ressort = Ressort::firstOrCreate(['name' => $v['ressort']], ['kuerzel' => $v['ressort_kuerzel'] ?? Str::upper(Str::substr($v['ressort'], 0, 3))]);
                $namen = $v['turniere'] ?? (filled($v['turnier'] ?? null) ? [$v['turnier']] : []);
                $gefunden = Turnier::whereIn('kurz', $namen)->orWhereIn('name', $namen)->get();
                $turniere = $gefunden->pluck('id')->all();
                foreach ($namen as $n) {
                    if (! $gefunden->contains(fn ($t) => $t->kurz === $n || $t->name === $n)) {
                        $unbekannt[$n] = true;
                    }
                }
                if (Vorlage::where('ressort_id', $ressort->id)->where('titel', $v['titel'])->exists()) {
                    $ueber++;

                    continue;
                }
                $neueVorlage = Vorlage::create([
                    'ressort_id' => $ressort->id,
                    'titel' => $v['titel'],
                    'beschreibung' => $v['beschreibung'] ?? null,
                    'anleitung' => $v['anleitung'] ?? null,
                    'tage_vorher' => (int) ($v['tage_vorher'] ?? 14),
                    'bezug' => in_array($v['bezug'] ?? null, \App\Support\Wann::BEZUG, true) ? $v['bezug'] : 'beginn',
                    'haeufigkeit' => ($v['haeufigkeit'] ?? null) === 'saison' ? 'saison' : 'turnier',
                    'aufwand_std' => $v['aufwand_std'] ?? null,
                    'prioritaet' => $v['prioritaet'] ?? 'normal',
                    'checkliste' => collect($v['checkliste'] ?? [])->map(fn ($p) => is_array($p) ? ($p['text'] ?? null) : $p)->filter()->values()->all() ?: null,
                    'aktiv' => (bool) ($v['aktiv'] ?? true),
                ]);
                $neueVorlage->setzeTurniere($neueVorlage->proSaison() ? [] : $turniere);
                $neu++;
            }
        });
        Aktivitaet::protokolliere('daten.import', "Jahresplan importiert: {$neu} neu, {$ueber} übersprungen");

        $hinweis = $unbekannt ? ' Turnierarten nicht gefunden: '.implode(', ', array_keys($unbekannt)).'. Diese Einträge gelten vorerst für alle Turnierarten, bitte im Jahresplan prüfen.' : '';

        return back()->with('erfolg', "{$neu} Aufgaben in den Jahresplan importiert, {$ueber} bereits vorhanden.".$hinweis);
    }

    /** CSV aller Aufgaben einer Ausgabe (fuer Excel). */
    public function aufgabenCsv(Ausgabe $ausgabe)
    {
        $aufgaben = $ausgabe->aufgaben()->with('ressort', 'verantwortlich')->orderBy('faellig_am')->get();

        return response()->streamDownload(function () use ($aufgaben) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, ['Ressort', 'Aufgabe', 'Verantwortlich', 'Fällig', 'Status', 'Aufwand geplant', 'Aufwand effektiv', 'Erledigt am'], ';');
            foreach ($aufgaben as $a) {
                fputcsv($h, [
                    $a->ressort->name, $a->titel, $a->verantwortlich?->name, $a->faellig_am?->format('d.m.Y'),
                    \App\Models\Aufgabe::STATUS[$a->status], $a->aufwand_geplant, $a->aufwand_effektiv, $a->erledigt_am?->format('d.m.Y'),
                ], ';');
            }
            fclose($h);
        }, Str::slug($ausgabe->titel).'-aufgaben.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Vollstaendiger Export als JSON (Archiv). */
    public function gesamtExport()
    {
        $sicherung = app(\App\Services\Sicherung::class);
        $daten = ['erstellt' => now()->toIso8601String(), 'tabellen' => []];
        foreach ($sicherung->tabellen() as $t) {
            if (in_array($t, ['password_reset_tokens', 'push_abos'], true)) {
                continue;
            }
            $daten['tabellen'][$t] = DB::table($t)->get()->map(function ($r) {
                $r = (array) $r;
                unset($r['password'], $r['remember_token'], $r['einladung_token']);

                return $r;
            })->all();
        }

        return response()->streamDownload(fn () => print (json_encode($daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), 'ok-tool-export-'.now()->format('Y-m-d').'.json', ['Content-Type' => 'application/json']);
    }
}
