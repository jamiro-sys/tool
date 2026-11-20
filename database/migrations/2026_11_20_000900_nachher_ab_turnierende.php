<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aufgaben und Jahresplan-Eintraege "nach dem Turnier" zaehlen ab Turnierende statt ab Turnierbeginn.
 * Das Datum bestehender Aufgaben bleibt gleich; nur der Abstand wird neu ab dem letzten Turniertag ausgedrueckt.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ausgaben = DB::table('ausgaben')->get(['id', 'datum_von', 'datum_bis'])->keyBy('id');
        $dauer = fn ($a) => $a && $a->datum_bis ? (int) Carbon::parse($a->datum_von)->startOfDay()->diffInDays(Carbon::parse($a->datum_bis)->startOfDay(), false) : 0;

        // Aufgaben: Abstand zum letzten Turniertag aus dem bestehenden Datum
        DB::table('aufgaben')->where('bezug', 'beginn')->where('tage_vorher', '<', 0)->orderBy('id')->get(['id', 'ausgabe_id', 'tage_vorher'])
            ->each(function ($a) use ($ausgaben, $dauer) {
                $tage = (int) $a->tage_vorher + $dauer($ausgaben[$a->ausgabe_id] ?? null);
                if ($tage <= 0) {
                    DB::table('aufgaben')->where('id', $a->id)->update(['tage_vorher' => $tage, 'bezug' => 'ende']);
                }
            });

        // Jahresplan: Turnierdauer aus dem letzten Turnier, in dem die Aufgabe vorkam (sonst ein Tag)
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

    public function down(): void
    {
        // Datum bleibt in beide Richtungen gleich; keine Rueckrechnung noetig
    }
};
