<?php

namespace App\Http\Controllers;

use App\Models\Ausgabe;
use App\Models\Turnier;
use App\Support\Saison;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Neue Saison planen: Daten aller Turnierarten einer Saison auf einmal waehlen,
 * danach fuehrt der bestehende Turnier-Assistent nacheinander durch die Turniere.
 */
class SaisonController extends Controller
{
    public const SESSION = 'saison_plan';

    public function create(Request $request)
    {
        Gate::authorize('ausgaben-verwalten');
        $saison = trim((string) $request->query('saison')) ?: Saison::vorschlag();
        $start = Saison::startjahr($saison) ?? (int) today()->year;
        $turniere = Turnier::with(['halle', 'ausgaben' => fn ($q) => $q->orderByDesc('datum_von')])->orderBy('name')->get();

        $zeilen = $turniere->map(function (Turnier $t) use ($saison, $start) {
            $vorhanden = $t->ausgaben->firstWhere('saison', $saison);
            $letzte = $t->ausgaben->first();
            // Vorschlag: ein Jahr nach dem letzten Turnier dieser Turnierart, gleicher Wochentag (52 Wochen)
            $von = null;
            $bis = null;
            if ($letzte) {
                $von = $letzte->datum_von->copy()->addWeeks(52);
                while (Saison::fuer($von) !== $saison && Saison::startjahr(Saison::fuer($von)) < $start) {
                    $von->addWeeks(52);
                }
                if (Saison::fuer($von) !== $saison) {
                    $von = null;
                }
                $bis = $von && $letzte->datum_bis ? $von->copy()->addDays($letzte->datum_von->diffInDays($letzte->datum_bis)) : null;
            }

            return ['turnier' => $t, 'vorhanden' => $vorhanden, 'letzte' => $letzte, 'von' => $von, 'bis' => $bis];
        });

        return view('saison.create', ['saison' => $saison, 'zeilen' => $zeilen]);
    }

    public function store(Request $request)
    {
        Gate::authorize('ausgaben-verwalten');
        $daten = $request->validate([
            'saison' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
            'turniere' => ['required', 'array', 'min:1'],
            'turniere.*.mit' => ['nullable', 'boolean'],
            'turniere.*.datum_von' => ['nullable', 'date'],
            'turniere.*.datum_bis' => ['nullable', 'date'],
        ], [
            'saison.regex' => 'Bitte die Saison so angeben: 2027/28.',
            'turniere.required' => 'Bitte mindestens ein Turnier wählen.',
        ]);
        $saison = $daten['saison'];

        $plan = [];
        $fehler = [];
        foreach ($daten['turniere'] as $id => $z) {
            if (empty($z['mit'])) {
                continue;
            }
            $turnier = Turnier::find($id);
            if (! $turnier) {
                continue;
            }
            if (Ausgabe::where('turnier_id', $turnier->id)->where('saison', $saison)->exists()) {
                $fehler["turniere.$id.datum_von"] = $turnier->name.' gibt es in dieser Saison schon.';

                continue;
            }
            if (empty($z['datum_von'])) {
                $fehler["turniere.$id.datum_von"] = 'Bitte den ersten Turniertag für '.$turnier->name.' angeben.';

                continue;
            }
            $von = Carbon::parse($z['datum_von']);
            $bis = filled($z['datum_bis'] ?? null) ? Carbon::parse($z['datum_bis']) : null;
            if (! Saison::enthaelt($saison, $von)) {
                $fehler["turniere.$id.datum_von"] = $von->format('d.m.Y').' liegt nicht in der Saison '.$saison.' (Juli bis Juni).';

                continue;
            }
            if ($bis && $bis->lt($von)) {
                $fehler["turniere.$id.datum_bis"] = 'Der letzte Turniertag kann nicht vor dem ersten liegen.';

                continue;
            }
            $plan[] = ['turnier_id' => $turnier->id, 'datum_von' => $von->toDateString(), 'datum_bis' => $bis?->toDateString()];
        }
        if ($fehler) {
            throw ValidationException::withMessages($fehler);
        }
        if (! $plan) {
            throw ValidationException::withMessages(['turniere' => 'Bitte mindestens ein Turnier mit Datum wählen.']);
        }

        usort($plan, fn ($a, $b) => strcmp($a['datum_von'], $b['datum_von']));
        $request->session()->put(self::SESSION, ['saison' => $saison, 'plan' => $plan, 'erstellt' => []]);

        return redirect()->to(self::naechsterSchritt($request));
    }

    /** Abbrechen: Plan verwerfen, bereits erstellte Turniere bleiben. */
    public function abbrechen(Request $request)
    {
        Gate::authorize('ausgaben-verwalten');
        $request->session()->forget(self::SESSION);

        return redirect()->route('ausgaben.index')->with('erfolg', 'Saisonplanung beendet. Fehlende Turniere kannst du jederzeit einzeln anlegen.');
    }

    /** Adresse des Assistenten fuer das naechste noch nicht erstellte Turnier des Plans, sonst null. */
    public static function naechsterSchritt(Request $request): ?string
    {
        $p = $request->session()->get(self::SESSION);
        if (! $p) {
            return null;
        }
        $offen = collect($p['plan'])->first(fn ($z) => ! in_array($z['turnier_id'], $p['erstellt'], true));

        return $offen ? route('ausgaben.create', ['turnier_id' => $offen['turnier_id'], 'datum_von' => $offen['datum_von'], 'datum_bis' => $offen['datum_bis'], 'saison' => $p['saison']]) : null;
    }

    /** Fuer die Kopfzeile im Assistenten: [Saison, Nummer, Anzahl] oder null, wenn kein Saison-Plan zu diesem Turnier passt. */
    public static function schritt(Request $request, ?int $turnierId): ?array
    {
        $p = $request->session()->get(self::SESSION);
        if (! $p || ! $turnierId) {
            return null;
        }
        $index = collect($p['plan'])->search(fn ($z) => $z['turnier_id'] === $turnierId);

        return $index === false ? null : [$p['saison'], $index + 1, count($p['plan'])];
    }
}
