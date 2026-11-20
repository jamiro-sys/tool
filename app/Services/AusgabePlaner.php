<?php

namespace App\Services;

use App\Models\Aktivitaet;
use App\Models\Ausgabe;
use App\Models\Aufgabe;
use App\Models\Erkenntnis;
use App\Models\Turnier;
use App\Models\Vorlage;
use App\Support\Wann;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Erstellt neue Ausgaben aus den Vorlagen und hilft beim Rueckblick.
 */
class AusgabePlaner
{
    /**
     * Vorschlag fuer den Assistenten: alle aktiven Vorlagen, die fuer das Turnier gelten,
     * mit den offenen Erkenntnissen und der verantwortlichen Person der letzten Ausgabe.
     */
    public function vorschlag(Turnier $turnier, Carbon $datum, ?Carbon $bis = null): Collection
    {
        $letzte = Ausgabe::where('turnier_id', $turnier->id)->orderByDesc('datum_von')->first();
        $letzteAufgaben = $letzte
            ? Aufgabe::where('ausgabe_id', $letzte->id)->whereNotNull('vorlage_id')->get()->keyBy('vorlage_id')
            : collect();

        return Vorlage::with(['ressort.mitglieder', 'turniere', 'erkenntnisse' => fn ($q) => $q->whereNull('umgesetzt_am')->with('user')])
            ->where('aktiv', true)
            ->get()
            ->filter(fn (Vorlage $v) => $v->giltFuer($turnier))
            ->map(function (Vorlage $v) use ($datum, $bis, $letzteAufgaben) {
                $vorher = $letzteAufgaben->get($v->id);
                $vorschlagPerson = $vorher?->verantwortlich_id ?? $v->ressort->leitung()?->id;

                return [
                    'vorlage' => $v,
                    'faellig_am' => $v->faelligkeitFuer($datum, $bis ?? $datum->copy()->addDay()),
                    'verantwortlich_id' => $vorschlagPerson,
                    'aufwand_vorjahr' => $vorher?->aufwand_effektiv,
                    'erkenntnisse' => $v->erkenntnisse,
                    'empfehlung' => $v->erkenntnisse->contains('vorschlag', 'streichen') ? 'pruefen' : 'uebernehmen',
                ];
            })
            ->sortBy(fn ($e) => [$e['vorlage']->ressort->sortierung, -$e['vorlage']->tage_vorher])
            ->values();
    }

    /**
     * @param  array<int, array{aktion?: string, titel?: string, faellig_am?: string, verantwortlich_id?: int|null}>  $auswahl
     */
    public function erstelle(Turnier $turnier, array $daten, array $auswahl, array $zusatz = []): Ausgabe
    {
        return DB::transaction(function () use ($turnier, $daten, $auswahl, $zusatz) {
            $datum = Carbon::parse($daten['datum_von']);

            $ausgabe = Ausgabe::create([
                'turnier_id' => $turnier->id,
                'halle_id' => $daten['halle_id'] ?? $turnier->halle_id,
                'saison' => $daten['saison'],
                'titel' => $daten['titel'] ?? ($turnier->name.' '.$daten['saison']),
                'datum_von' => $datum,
                'datum_bis' => $daten['datum_bis'] ?? null,
                'status' => 'planung',
            ]);

            $vorlagen = Vorlage::whereIn('id', array_keys($auswahl))->get()->keyBy('id');
            $anzahl = 0;

            foreach ($auswahl as $vorlageId => $wahl) {
                $vorlage = $vorlagen->get($vorlageId);
                if (! $vorlage || ($wahl['aktion'] ?? 'uebernehmen') === 'weglassen') {
                    continue;
                }

                // Abstand aus dem Jahresplan; wurde das Datum im Assistenten geaendert, gilt der Abstand zu diesem Datum
                $berechnet = Wann::fuer($ausgabe, (int) $vorlage->tage_vorher, $vorlage->bezug ?? 'beginn');
                $vorschlag = $vorlage->faelligkeitFuer($datum, $datum->copy()->addDay());
                $gewaehlt = filled($wahl['faellig_am'] ?? null) ? Carbon::parse($wahl['faellig_am']) : null;
                $eigenes = $gewaehlt && ! $gewaehlt->isSameDay($berechnet) && ! $gewaehlt->isSameDay($vorschlag);
                Aufgabe::create([
                    'ausgabe_id' => $ausgabe->id,
                    'vorlage_id' => $vorlage->id,
                    'ressort_id' => $vorlage->ressort_id,
                    'verantwortlich_id' => $wahl['verantwortlich_id'] ?? null,
                    'titel' => filled($wahl['titel'] ?? null) ? $wahl['titel'] : $vorlage->titel,
                    'beschreibung' => $vorlage->beschreibung,
                    'anleitung' => $vorlage->anleitung,
                    'faellig_am' => $eigenes ? $gewaehlt : $berechnet,
                    'tage_vorher' => $eigenes ? Wann::ausDatum($ausgabe, $gewaehlt) : (int) $vorlage->tage_vorher,
                    'bezug' => $eigenes ? 'beginn' : ($vorlage->bezug ?? 'beginn'),
                    'prioritaet' => $vorlage->prioritaet,
                    'aufwand_geplant' => $vorlage->aufwand_std,
                    'checkliste' => collect($vorlage->checkliste ?? [])->map(fn ($p) => ['text' => is_array($p) ? $p['text'] : $p, 'erledigt' => false])->values()->all() ?: null,
                ]);
                $anzahl++;
            }

            foreach ($zusatz as $neu) {
                if (blank($neu['titel'] ?? null) || blank($neu['ressort_id'] ?? null)) {
                    continue;
                }
                Aufgabe::create([
                    'ausgabe_id' => $ausgabe->id,
                    'ressort_id' => $neu['ressort_id'],
                    'titel' => $neu['titel'],
                    'faellig_am' => $neu['faellig_am'] ?? null,
                    'tage_vorher' => filled($neu['faellig_am'] ?? null) ? Wann::ausDatum($ausgabe, Carbon::parse($neu['faellig_am'])) : null,
                    'verantwortlich_id' => $neu['verantwortlich_id'] ?? null,
                ]);
                $anzahl++;
            }

            Aktivitaet::protokolliere('ausgabe.erstellt', "Ausgabe {$ausgabe->titel} mit {$anzahl} Aufgaben erstellt", $ausgabe);

            return $ausgabe;
        });
    }

    /**
     * Uebernimmt eine Erkenntnis in die Vorlage (oder legt eine neue Vorlage an).
     */
    public function uebernehme(Erkenntnis $erkenntnis, array $aenderungen = []): ?Vorlage
    {
        return DB::transaction(function () use ($erkenntnis, $aenderungen) {
            $vorlage = $erkenntnis->vorlage;

            switch ($erkenntnis->vorschlag) {
                case 'streichen':
                    if ($vorlage) {
                        $this->aendereVorlage($vorlage, [
                            'aktiv' => false,
                            'deaktiviert_am' => now(),
                            'deaktiviert_grund' => mb_substr($erkenntnis->text, 0, 250),
                        ], 'Gestrichen aufgrund Erkenntnis');
                    }
                    break;

                case 'neu':
                    $aufgabe = $erkenntnis->aufgabe;
                    $vorlage = Vorlage::create([
                        'ressort_id' => $erkenntnis->ressort_id,
                        'titel' => $aenderungen['titel'] ?? $aufgabe?->titel ?? mb_substr($erkenntnis->text, 0, 120),
                        'beschreibung' => $aufgabe?->beschreibung,
                        'anleitung' => $aenderungen['anleitung'] ?? $erkenntnis->text,
                        'tage_vorher' => $aenderungen['tage_vorher'] ?? $aufgabe?->tage_vorher ?? ($aufgabe && $aufgabe->faellig_am ? Wann::ausDatum($aufgabe->ausgabe, $aufgabe->faellig_am) : 14),
                        'bezug' => $aenderungen['bezug'] ?? ($aufgabe?->tage_vorher !== null ? ($aufgabe->bezug ?? 'beginn') : 'beginn'),
                        'aufwand_std' => $aufgabe?->aufwand_effektiv,
                        'aktiv_seit' => $erkenntnis->ausgabe?->saison,
                    ]);
                    if (filled($aenderungen['turnier_id'] ?? null)) {
                        $vorlage->setzeTurniere([$aenderungen['turnier_id']]);
                    }
                    if ($aufgabe) {
                        $aufgabe->update(['vorlage_id' => $vorlage->id]);
                    }
                    $erkenntnis->vorlage_id = $vorlage->id;
                    break;

                case 'anpassen':
                    if ($vorlage && $aenderungen) {
                        if (array_key_exists('turnier_id', $aenderungen)) {
                            $vorlage->setzeTurniere([$aenderungen['turnier_id']]);
                            unset($aenderungen['turnier_id']);
                        }
                        $this->aendereVorlage($vorlage, $aenderungen, 'Angepasst aufgrund Erkenntnis');
                    } elseif ($vorlage) {
                        $zusatz = "\n\nHinweis aus ".($erkenntnis->ausgabe?->titel ?? 'Rückblick').': '.$erkenntnis->text;
                        $this->aendereVorlage($vorlage, ['anleitung' => trim(($vorlage->anleitung ?? '').$zusatz)], 'Hinweis ergänzt');
                    }
                    break;
            }

            $erkenntnis->umgesetzt_am = now();
            $erkenntnis->umgesetzt_von = auth()->id();
            $erkenntnis->save();

            Aktivitaet::protokolliere('erkenntnis.umgesetzt', 'Erkenntnis umgesetzt: '.$erkenntnis->text, $erkenntnis);

            return $vorlage;
        });
    }

    public function aendereVorlage(Vorlage $vorlage, array $neu, ?string $notiz = null): Vorlage
    {
        $felder = array_keys($neu);
        $vorher = collect($vorlage->only($felder))->map(fn ($w) => $w instanceof \DateTimeInterface ? $w->format('c') : $w)->all();
        $vorlage->fill($neu);
        $dirty = $vorlage->getDirty();
        if (! $dirty) {
            return $vorlage;
        }
        $vorlage->save();

        $vorlage->versionen()->create([
            'user_id' => auth()->id(),
            'vorher' => array_intersect_key($vorher, $dirty),
            'nachher' => collect($dirty)->map(fn ($w) => $w instanceof \DateTimeInterface ? $w->format('c') : $w)->all(),
            'notiz' => $notiz,
        ]);

        return $vorlage;
    }

    /** Aendert den Status der Ausgaben automatisch anhand des Datums. */
    public function statusAktualisieren(): array
    {
        $geaendert = [];
        foreach (Ausgabe::whereIn('status', ['planung', 'laufend'])->get() as $ausgabe) {
            $ende = ($ausgabe->datum_bis ?? $ausgabe->datum_von)->copy()->endOfDay();
            $neu = null;
            if ($ausgabe->status === 'planung' && $ausgabe->tageBisTurnier() <= 7 && now()->lte($ende)) {
                $neu = 'laufend';
            }
            if (now()->gt($ende)) {
                $neu = 'rueckblick';
            }
            if ($neu && $neu !== $ausgabe->status) {
                $ausgabe->update(['status' => $neu]);
                $geaendert[] = [$ausgabe, $neu];
            }
        }

        return $geaendert;
    }

    /** Aufgabe in den Jahresplan aufnehmen: kommt ab dem naechsten Turnier wieder. */
    public function inJahresplan(Aufgabe $aufgabe, ?array $turniere = null): Vorlage
    {
        if ($aufgabe->vorlage) {
            if (! $aufgabe->vorlage->aktiv) {
                $this->aendereVorlage($aufgabe->vorlage, ['aktiv' => true, 'deaktiviert_am' => null, 'deaktiviert_grund' => null], 'Wieder im Jahresplan');
            }
            if ($turniere !== null) {
                $aufgabe->vorlage->setzeTurniere($turniere);
            }

            return $aufgabe->vorlage;
        }
        $aufgabe->loadMissing('ausgabe');
        $vorlage = Vorlage::create([
            'ressort_id' => $aufgabe->ressort_id,
            'titel' => $aufgabe->titel,
            'beschreibung' => $aufgabe->beschreibung,
            'anleitung' => $aufgabe->anleitung,
            'checkliste' => collect($aufgabe->checkliste ?? [])->map(fn ($p) => is_array($p) ? ($p['text'] ?? null) : $p)->filter()->values()->all() ?: null,
            'tage_vorher' => $aufgabe->tage_vorher ?? ($aufgabe->faellig_am ? Wann::ausDatum($aufgabe->ausgabe, $aufgabe->faellig_am) : 14),
            'bezug' => $aufgabe->tage_vorher !== null ? ($aufgabe->bezug ?? 'beginn') : 'beginn',
            'aufwand_std' => $aufgabe->aufwand_effektiv ?? $aufgabe->aufwand_geplant,
            'prioritaet' => $aufgabe->prioritaet ?? 'normal',
            'aktiv' => true,
            'aktiv_seit' => $aufgabe->ausgabe->saison,
        ]);
        if ($turniere) {
            $vorlage->setzeTurniere($turniere);
        }
        $aufgabe->update(['vorlage_id' => $vorlage->id]);
        $aufgabe->setRelation('vorlage', $vorlage);
        Aktivitaet::protokolliere('vorlage.neu', 'Im Jahresplan: '.$aufgabe->titel, $vorlage);

        return $vorlage;
    }

    /** Aus dem Jahresplan nehmen: kommt bei neuen Turnieren nicht mehr, Verlauf bleibt. */
    public function ausJahresplan(Vorlage $vorlage, ?string $grund = null): void
    {
        if ($vorlage->aktiv) {
            $this->aendereVorlage($vorlage, ['aktiv' => false, 'deaktiviert_am' => now(), 'deaktiviert_grund' => $grund], 'Aus dem Jahresplan genommen');
        }
    }
}
