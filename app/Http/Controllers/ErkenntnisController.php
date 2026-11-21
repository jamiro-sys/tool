<?php

namespace App\Http\Controllers;

use App\Models\Aufgabe;
use App\Models\Erkenntnis;
use App\Models\Ressort;
use App\Services\AusgabePlaner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ErkenntnisController extends Controller
{
    public function __construct(protected AusgabePlaner $planer) {}

    public function store(Request $request)
    {
        Gate::authorize('schreiben');
        $daten = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
            'vorschlag' => ['required', Rule::in(array_keys(Erkenntnis::VORSCHLAEGE))],
            'aufgabe_id' => ['nullable', 'exists:aufgaben,id'],
            'ressort_id' => ['required_without:aufgabe_id', 'nullable', 'exists:ressorts,id'],
            'ausgabe_id' => ['nullable', 'exists:ausgaben,id'],
            'vorlage_id' => ['nullable', 'exists:vorlagen,id'],
        ]);

        if (! empty($daten['aufgabe_id'])) {
            $aufgabe = Aufgabe::findOrFail($daten['aufgabe_id']);
            $daten['ressort_id'] = $aufgabe->ressort_id;
            $daten['ausgabe_id'] = $aufgabe->ausgabe_id;
            $daten['vorlage_id'] = $aufgabe->vorlage_id;
        } elseif (! empty($daten['vorlage_id'])) {
            // Jahresplan-Eintrag bestimmt das Ressort (kein Eintrag eines fremden Ressorts)
            $daten['ressort_id'] = \App\Models\Vorlage::findOrFail($daten['vorlage_id'])->ressort_id;
        }

        Erkenntnis::create($daten + ['user_id' => $request->user()->id]);

        return back()->with('erfolg', 'Danke! Die Erkenntnis ist für das nächste Mal festgehalten.');
    }

    public function uebernehmen(Request $request, Erkenntnis $erkenntnis)
    {
        Gate::authorize('ressort-verwalten', $erkenntnis->ressort);
        abort_if($erkenntnis->vorlage && $erkenntnis->vorlage->ressort_id !== $erkenntnis->ressort_id, 403);
        if ($erkenntnis->umgesetzt_am) {
            return back()->with('erfolg', 'Schon übernommen.');
        }
        $aenderungen = $request->validate([
            'titel' => ['nullable', 'string', 'max:200'],
            'tage_vorher' => ['nullable', 'integer', 'min:-365', 'max:730'],
            'bezug' => ['nullable', \Illuminate\Validation\Rule::in(\App\Support\Wann::BEZUG)],
            'anleitung' => ['nullable', 'string', 'max:20000'],
            'aufwand_std' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'turnier_id' => ['nullable', 'exists:turniere,id'],
        ]);
        $vorlage = $this->planer->uebernehme($erkenntnis, array_filter($aenderungen, fn ($w) => $w !== null));

        $meldung = match ($erkenntnis->vorschlag) {
            'streichen' => 'Kommt nicht mehr bei neuen Turnieren.',
            'neu' => 'In den Jahresplan aufgenommen.',
            'anpassen' => 'Jahresplan angepasst.',
            default => 'Als erledigt markiert.',
        };

        return back()->with('erfolg', $meldung)->with('vorlage_link', $vorlage ? route('vorlagen.show', $vorlage) : null);
    }

    public function destroy(Request $request, Erkenntnis $erkenntnis)
    {
        abort_unless($erkenntnis->user_id === $request->user()->id || Gate::allows('ressort-verwalten', $erkenntnis->ressort), 403);
        $erkenntnis->delete();

        return back()->with('erfolg', 'Erkenntnis verworfen.');
    }
}
