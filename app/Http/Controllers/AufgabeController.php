<?php

namespace App\Http\Controllers;

use App\Models\Aktivitaet;
use App\Models\Ausgabe;
use App\Models\Aufgabe;
use App\Models\Kommentar;
use App\Models\Ressort;
use App\Models\User;
use App\Services\AusgabePlaner;
use App\Support\Wann;
use App\Services\Benachrichtiger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AufgabeController extends Controller
{
    public function __construct(protected Benachrichtiger $benachrichtiger) {}

    public function index(Request $request, Ausgabe $ausgabe)
    {
        $ansicht = in_array($request->query('ansicht'), ['liste', 'board'], true) ? $request->query('ansicht') : 'liste';

        $query = $ausgabe->aufgaben()->with(['ressort', 'verantwortlich', 'begleitung']);
        if ($request->filled('ressort')) {
            $query->where('ressort_id', $request->integer('ressort'));
        }
        if ($request->filled('person')) {
            $request->query('person') === 'keine'
                ? $query->whereNull('verantwortlich_id')
                : $query->where('verantwortlich_id', $request->integer('person'));
        }
        if ($request->filled('status')) {
            $request->query('status') === 'offen_alle'
                ? $query->offen()
                : $query->where('status', $request->query('status'));
        } elseif ($ansicht === 'liste' && ! $request->boolean('alle')) {
            $query->where('status', '!=', 'entfaellt');
        }
        if ($request->filled('q')) {
            $q = '%'.$request->query('q').'%';
            $query->where(fn ($w) => $w->where('titel', 'like', $q)->orWhere('beschreibung', 'like', $q));
        }
        if ($request->query('nur') === 'ueberfaellig') {
            $query->offen()->whereDate('faellig_am', '<', today());
        }

        $aufgaben = $query->orderByRaw('faellig_am is null')->orderBy('faellig_am')->orderBy('titel')->get();

        return view('aufgaben.index', [
            'ausgabe' => $ausgabe->load('turnier', 'halle'),
            'aufgaben' => $aufgaben,
            'ansicht' => $ansicht,
            'ressorts' => Ressort::orderBy('sortierung')->get(),
            'personen' => User::where('aktiv', true)->orderBy('name')->get(),
            'filter' => $request->only(['ressort', 'person', 'status', 'q', 'nur', 'alle']),
        ]);
    }

    public function show(Aufgabe $aufgabe)
    {
        $aufgabe->load(['ausgabe.turnier', 'ressort.mitglieder', 'verantwortlich', 'begleitung', 'kommentare.user', 'erkenntnisse.user', 'dokumente', 'vorlage.turniere']);

        $frueher = $aufgabe->vorlage_id
            ? Aufgabe::with('ausgabe', 'verantwortlich')->where('vorlage_id', $aufgabe->vorlage_id)->where('id', '!=', $aufgabe->id)->latest('faellig_am')->limit(5)->get()
            : collect();

        return view('aufgaben.show', [
            'aufgabe' => $aufgabe,
            'frueher' => $frueher,
            'personen' => User::where('aktiv', true)->orderBy('name')->get(),
            'ressorts' => Ressort::orderBy('sortierung')->get(),
        ]);
    }

    public function store(Request $request, Ausgabe $ausgabe)
    {
        $daten = $this->validiere($request);
        $ressort = Ressort::findOrFail($daten['ressort_id']);
        abort_unless(Gate::allows('ressort-mitarbeiten', $ressort) || Gate::allows('ressort-verwalten', $ressort), 403);

        $jedesJahr = $request->input('wiederkehrend') === 'jedes_jahr' && Gate::allows('ressort-verwalten', $ressort);
        $turniere = $jedesJahr ? $this->turnierWahl($request) : [];
        unset($daten['wiederkehrend']);
        $this->wannAnwenden($request, $daten, $ausgabe);
        $aufgabe = $ausgabe->aufgaben()->create($daten);
        Aktivitaet::protokolliere('aufgabe.erstellt', 'Aufgabe erstellt: '.$aufgabe->titel, $aufgabe);
        $this->meldeZuweisung($aufgabe, null);
        if ($jedesJahr) {
            app(AusgabePlaner::class)->inJahresplan($aufgabe, $turniere);
        }
        $meldung = $jedesJahr ? 'Aufgabe erstellt und in den Jahresplan aufgenommen.' : 'Aufgabe erstellt.';

        // Zurueck auf die Seite, von der aus erfasst wurde (nur Pfade dieses Tools)
        $zurueck = (string) $request->input('zurueck');
        if (str_starts_with($zurueck, '/') && ! str_starts_with($zurueck, '//')) {
            return redirect($zurueck)->with('erfolg', $meldung);
        }

        return redirect()->route('aufgaben.show', $aufgabe)->with('erfolg', $meldung);
    }

    public function update(Request $request, Aufgabe $aufgabe)
    {
        Gate::authorize('aufgabe-bearbeiten', $aufgabe);
        $daten = $this->validiere($request, true);
        $vorher = $aufgabe->verantwortlich_id;

        if (isset($daten['checkliste_text'])) {
            $alt = collect($aufgabe->checkliste ?? [])->keyBy('text');
            $daten['checkliste'] = collect(preg_split('/\r?\n/', $daten['checkliste_text']))
                ->map(fn ($z) => trim($z))->filter()
                ->map(fn ($z) => ['text' => $z, 'erledigt' => (bool) ($alt[$z]['erledigt'] ?? false)])
                ->values()->all() ?: null;
            unset($daten['checkliste_text']);
        }

        $this->wannAnwenden($request, $daten, $aufgabe->ausgabe);
        $warImPlan = (bool) $aufgabe->vorlage?->aktiv;
        $aufgabe->update($daten);
        $this->setzeErledigt($aufgabe);

        // Wie oft? Jahresplan anpassen (nur Ressortleitung/Admin)
        $hinweis = null;
        $darfPlan = Gate::allows('ressort-verwalten', $aufgabe->ressort);
        if ($request->filled('wiederkehrend') && $darfPlan) {
            $planer = app(AusgabePlaner::class);
            if ($request->input('wiederkehrend') === 'jedes_jahr') {
                $planer->inJahresplan($aufgabe, $this->turnierWahl($request));
                $hinweis = ' Kommt jedes Jahr wieder'.($aufgabe->vorlage->turnierartText() ? ' ('.$aufgabe->vorlage->turnierartText().')' : '').'.';
            } elseif ($aufgabe->vorlage?->aktiv) {
                $planer->ausJahresplan($aufgabe->vorlage, 'Bei der Aufgabe auf «Nur dieses Turnier» gestellt');
                $hinweis = ' Kommt bei neuen Turnieren nicht mehr.';
            }
        }

        // Aenderungen auch im Jahresplan uebernehmen (Haekchen, nur wenn die Aufgabe schon jedes Jahr kam)
        if ($warImPlan && $darfPlan && $request->boolean('jahresplan_mit') && $request->input('wiederkehrend', 'jedes_jahr') === 'jedes_jahr') {
            $aufgabe->refresh();
            $neu = array_filter([
                'titel' => array_key_exists('titel', $daten) ? $aufgabe->titel : null,
                'beschreibung' => array_key_exists('beschreibung', $daten) ? $aufgabe->beschreibung : null,
                'aufwand_std' => array_key_exists('aufwand_geplant', $daten) ? $aufgabe->aufwand_geplant : null,
                'prioritaet' => array_key_exists('prioritaet', $daten) ? $aufgabe->prioritaet : null,
            ], fn ($w) => $w !== null);
            if ($request->has('wann_gesendet') && $aufgabe->faellig_am) {
                $neu['tage_vorher'] = $aufgabe->tage_vorher ?? Wann::ausDatum($aufgabe->ausgabe, $aufgabe->faellig_am);
                $neu['bezug'] = $aufgabe->tage_vorher !== null ? $aufgabe->bezug : 'beginn';
            }
            if ($neu) {
                app(AusgabePlaner::class)->aendereVorlage($aufgabe->vorlage, $neu, 'Von Aufgabe übernommen ('.$aufgabe->ausgabe->titel.')');
                $hinweis = ($hinweis ?? '').' Auch im Jahresplan übernommen.';
            }
        }

        Aktivitaet::protokolliere('aufgabe.geaendert', 'Aufgabe geändert: '.$aufgabe->titel, $aufgabe);
        $this->meldeZuweisung($aufgabe, $vorher);

        return back()->with('erfolg', 'Gespeichert.'.$hinweis);
    }

    /** Schneller Statuswechsel (auch per fetch aus Listen und Board). */
    public function status(Request $request, Aufgabe $aufgabe)
    {
        Gate::authorize('aufgabe-bearbeiten', $aufgabe);
        $daten = $request->validate([
            'status' => ['required', Rule::in(array_keys(Aufgabe::STATUS))],
            'aufwand_effektiv' => ['nullable', 'numeric', 'min:0', 'max:999'],
        ]);
        $alt = $aufgabe->status;
        $aufgabe->fill($daten);
        $this->setzeErledigt($aufgabe);
        $aufgabe->save();

        if ($alt !== $aufgabe->status) {
            Aktivitaet::protokolliere('aufgabe.status', $aufgabe->titel.': '.Aufgabe::STATUS[$aufgabe->status], $aufgabe, ['von' => $alt, 'nach' => $aufgabe->status]);
            if ($aufgabe->status === 'erledigt') {
                $leitung = $aufgabe->ressort->leitung();
                $this->benachrichtiger->sende(
                    $leitung ? [$leitung] : [], 'ressort', 'Erledigt: '.$aufgabe->titel,
                    $request->user()->name.' hat die Aufgabe abgeschlossen.', route('aufgaben.show', $aufgabe), $request->user()
                );
            }
        }

        if ($request->expectsJson()) {
            $f = $aufgabe->ausgabe->fortschritt();

            return response()->json(['ok' => true, 'status' => $aufgabe->status, 'label' => Aufgabe::STATUS[$aufgabe->status], 'fortschritt' => $f]);
        }

        return back()->with('erfolg', $aufgabe->status === 'erledigt' ? 'Stark, erledigt!' : 'Status aktualisiert.');
    }

    public function checkpunkt(Request $request, Aufgabe $aufgabe)
    {
        Gate::authorize('aufgabe-bearbeiten', $aufgabe);
        $index = $request->validate(['index' => ['required', 'integer', 'min:0']])['index'];
        $liste = $aufgabe->checkliste ?? [];
        abort_unless(isset($liste[$index]), 404);
        $liste[$index]['erledigt'] = ! ($liste[$index]['erledigt'] ?? false);
        $aufgabe->update(['checkliste' => $liste]);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'erledigt' => $liste[$index]['erledigt'], 'fortschritt' => $aufgabe->checklisteFortschritt()])
            : back();
    }

    public function destroy(Aufgabe $aufgabe)
    {
        Gate::authorize('aufgabe-verwalten', $aufgabe);
        $ausgabe = $aufgabe->ausgabe;
        Aktivitaet::protokolliere('aufgabe.geloescht', 'Aufgabe gelöscht: '.$aufgabe->titel, $ausgabe);
        $aufgabe->delete();

        return redirect()->route('aufgaben.index', $ausgabe)->with('erfolg', 'Aufgabe gelöscht.');
    }

    public function kommentar(Request $request, Aufgabe $aufgabe)
    {
        Gate::authorize('schreiben');
        $daten = $request->validate(['text' => ['required', 'string', 'max:5000']]);
        $kommentar = $aufgabe->kommentare()->create(['user_id' => $request->user()->id, 'text' => $daten['text']]);

        // Erwaehnungen mit @Vorname
        $erwaehnt = User::where('aktiv', true)->get()->filter(
            fn ($u) => preg_match('/@'.preg_quote(strtok($u->name, ' '), '/').'\b/iu', $daten['text'])
        );
        $empfaenger = collect([$aufgabe->verantwortlich, $aufgabe->begleitung])->filter()->merge($erwaehnt);
        $this->benachrichtiger->sende(
            $empfaenger, 'kommentar', 'Kommentar zu: '.$aufgabe->titel,
            $request->user()->name.': '.mb_strimwidth($kommentar->text, 0, 140, '...'),
            route('aufgaben.show', $aufgabe).'#kommentare', $request->user()
        );

        return redirect(route('aufgaben.show', $aufgabe).'#kommentare');
    }

    public function kommentarLoeschen(Request $request, Kommentar $kommentar)
    {
        abort_unless($kommentar->user_id === $request->user()->id || $request->user()->istAdmin(), 403);
        $aufgabe = $kommentar->aufgabe;
        $kommentar->delete();

        return redirect(route('aufgaben.show', $aufgabe).'#kommentare');
    }

    protected function validiere(Request $request, bool $teilweise = false): array
    {
        $req = $teilweise ? 'sometimes' : 'required';

        return $request->validate([
            'titel' => [$req, 'string', 'max:200'],
            'ressort_id' => [$req, 'exists:ressorts,id'],
            'beschreibung' => ['nullable', 'string', 'max:10000'],
            'anleitung' => ['nullable', 'string', 'max:20000'],
            'faellig_am' => ['nullable', 'date'],
            'tage_vorher' => ['nullable', 'integer', 'min:-365', 'max:730'],
            'bezug' => ['nullable', Rule::in(Wann::BEZUG)],
            'verantwortlich_id' => ['nullable', 'exists:users,id'],
            'begleitung_id' => ['nullable', 'exists:users,id'],
            'status' => ['sometimes', Rule::in(array_keys(Aufgabe::STATUS))],
            'prioritaet' => ['sometimes', Rule::in(['tief', 'normal', 'hoch'])],
            'aufwand_geplant' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'aufwand_effektiv' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'checkliste_text' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /** Faelligkeit aus "Wann": Abstand zum Turnier ergibt das Datum; ohne Abstand gilt das fixe Datum (oder keines). */
    protected function wannAnwenden(Request $request, array &$daten, Ausgabe $ausgabe): void
    {
        if (! $request->has('wann_gesendet')) {
            unset($daten['tage_vorher'], $daten['bezug']);
            // Datum aus einem anderen Formular: Abstand zum Turnierbeginn daraus ableiten
            if (array_key_exists('faellig_am', $daten)) {
                $daten['tage_vorher'] = $daten['faellig_am'] ? Wann::ausDatum($ausgabe, \Carbon\Carbon::parse($daten['faellig_am'])) : null;
                $daten['bezug'] = 'beginn';
            }

            return;
        }
        $tage = $daten['tage_vorher'] ?? null;
        $daten['bezug'] = $daten['bezug'] ?? 'beginn';
        if ($tage !== null) {
            $daten['faellig_am'] = Wann::fuer($ausgabe, (int) $tage, $daten['bezug']);
        } else {
            $daten['tage_vorher'] = null;
            $daten['faellig_am'] = $daten['faellig_am'] ?? null;
        }
    }

    /** Gewaehlte Turnierarten bei "Jedes Jahr"; wurden Haekchen angezeigt, muss mindestens eines gesetzt sein. */
    protected function turnierWahl(Request $request): array
    {
        return $request->validate([
            'turniere' => ['required_with:turniere_gesendet', 'array'],
            'turniere.*' => ['integer', 'exists:turniere,id'],
        ], ['turniere.required_with' => 'Bitte mindestens ein Turnier anhaken.'])['turniere'] ?? [];
    }

    protected function setzeErledigt(Aufgabe $aufgabe): void
    {
        if ($aufgabe->status === 'erledigt' && ! $aufgabe->erledigt_am) {
            $aufgabe->erledigt_am = now();
            $aufgabe->erledigt_von = auth()->id();
        } elseif ($aufgabe->status !== 'erledigt' && $aufgabe->erledigt_am) {
            $aufgabe->erledigt_am = null;
            $aufgabe->erledigt_von = null;
        }
        if ($aufgabe->isDirty()) {
            $aufgabe->save();
        }
    }

    protected function meldeZuweisung(Aufgabe $aufgabe, ?int $vorher): void
    {
        if ($aufgabe->verantwortlich_id && $aufgabe->verantwortlich_id !== $vorher) {
            $this->benachrichtiger->sende(
                $aufgabe->verantwortlich, 'zugewiesen', 'Neue Aufgabe: '.$aufgabe->titel,
                'Fällig '.$aufgabe->faelligText().' im Ressort '.$aufgabe->ressort->name.'.',
                route('aufgaben.show', $aufgabe), auth()->user()
            );
        }
    }

    /** Einmalige Aufgabe in den Jahresplan aufnehmen: kommt ab jetzt bei jedem neuen Turnier. */
    public function wiederholen(Aufgabe $aufgabe, AusgabePlaner $planer)
    {
        Gate::authorize('ressort-verwalten', $aufgabe->ressort);
        abort_if($aufgabe->vorlage_id, 409);
        $vorlage = $planer->inJahresplan($aufgabe);

        return back()->with('erfolg', 'Im Jahresplan: kommt ab jetzt bei jedem neuen Turnier, '.$vorlage->zeitpunktText().'.');
    }

    /** Rueckblick: kommt die Aufgabe naechstes Jahr wieder? (Ja nimmt sie in den Jahresplan, Nein nimmt sie heraus) */
    public function naechstesJahr(Request $request, Aufgabe $aufgabe, AusgabePlaner $planer)
    {
        Gate::authorize('ressort-verwalten', $aufgabe->ressort);
        $ja = $request->boolean('wert');
        if ($ja) {
            $planer->inJahresplan($aufgabe);
        } elseif ($aufgabe->vorlage) {
            $planer->ausJahresplan($aufgabe->vorlage, 'Im Rückblick '.$aufgabe->ausgabe->saison);
        }

        return response()->json(['wert' => $ja]);
    }
}
