<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Datenumstellungen, die sowohl bei Migrationen als auch beim Einspielen einer aelteren Sicherung noetig sind.
 * Alle Schritte aendern keine Faelligkeitsdaten, sie druecken sie nur neu aus.
 */
class Datenpflege
{
    /** Aufgaben mit Datum, aber ohne Abstand: Abstand zum Turnierbeginn aus dem Datum (2026_11_20_000800). */
    public static function faelligkeitRelativ(): void
    {
        $beginn = DB::table('ausgaben')->pluck('datum_von', 'id');
        DB::table('aufgaben')->whereNotNull('faellig_am')->whereNull('tage_vorher')->orderBy('id')->select(['id', 'ausgabe_id', 'faellig_am'])->each(function ($a) use ($beginn) {
            if (! isset($beginn[$a->ausgabe_id])) {
                return;
            }
            $tage = (int) Carbon::parse($a->faellig_am)->startOfDay()->diffInDays(Carbon::parse($beginn[$a->ausgabe_id])->startOfDay(), false);
            DB::table('aufgaben')->where('id', $a->id)->update(['tage_vorher' => $tage, 'bezug' => 'beginn']);
        });
    }

    /** "Nachher" zaehlt ab Turnierende (2026_11_20_000900). */
    public static function nachherAbEnde(): void
    {
        $ausgaben = DB::table('ausgaben')->get(['id', 'datum_von', 'datum_bis'])->keyBy('id');
        $dauer = fn ($a) => $a && $a->datum_bis ? (int) Carbon::parse($a->datum_von)->startOfDay()->diffInDays(Carbon::parse($a->datum_bis)->startOfDay(), false) : 0;

        DB::table('aufgaben')->where('bezug', 'beginn')->where('tage_vorher', '<', 0)->orderBy('id')->get(['id', 'ausgabe_id', 'tage_vorher'])
            ->each(function ($a) use ($ausgaben, $dauer) {
                $tage = (int) $a->tage_vorher + $dauer($ausgaben[$a->ausgabe_id] ?? null);
                if ($tage <= 0) {
                    DB::table('aufgaben')->where('id', $a->id)->update(['tage_vorher' => $tage, 'bezug' => 'ende']);
                }
            });

        DB::table('vorlagen')->where('bezug', 'beginn')->where('tage_vorher', '<', 0)->orderBy('id')->get(['id', 'tage_vorher'])
            ->each(function ($v) use ($ausgaben, $dauer) {
                $letzte = DB::table('aufgaben')->join('ausgaben', 'ausgaben.id', '=', 'aufgaben.ausgabe_id')
                    ->where('aufgaben.vorlage_id', $v->id)->orderByDesc('ausgaben.datum_von')->value('ausgaben.id');
                $tage = (int) $v->tage_vorher + ($letzte ? $dauer($ausgaben[$letzte] ?? null) : 0);
                if ($tage <= 0) {
                    DB::table('vorlagen')->where('id', $v->id)->update(['tage_vorher' => $tage, 'bezug' => 'ende']);
                }
            });
    }

    /** Einzelne Turnierart der Vorlage in die Mehrfachauswahl uebernehmen (2026_11_20_000700). */
    public static function turnierartenInPivot(): void
    {
        DB::table('vorlagen')->whereNotNull('turnier_id')->orderBy('id')->each(function ($v) {
            DB::table('turnier_vorlage')->insertOrIgnore(['vorlage_id' => $v->id, 'turnier_id' => $v->turnier_id]);
        });
    }
}
