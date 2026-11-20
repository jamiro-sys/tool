<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Faelligkeit als Abstand zum Turnier: positiv = vorher, 0 = am Tag, negativ = nachher.
        // bezug: beginn (erster Turniertag) oder ende (letzter Turniertag). tage_vorher null = fixes Datum oder ohne Termin.
        Schema::table('aufgaben', function (Blueprint $table) {
            $table->integer('tage_vorher')->nullable()->after('faellig_am');
            $table->string('bezug', 8)->default('beginn')->after('tage_vorher');
        });
        Schema::table('vorlagen', function (Blueprint $table) {
            $table->string('bezug', 8)->default('beginn')->after('tage_vorher');
        });

        // Bestehende Aufgaben mit Datum werden relativ zum Turnierbeginn (Datum bleibt gleich)
        $beginn = DB::table('ausgaben')->pluck('datum_von', 'id');
        DB::table('aufgaben')->whereNotNull('faellig_am')->orderBy('id')->select(['id', 'ausgabe_id', 'faellig_am'])->each(function ($a) use ($beginn) {
            if (! isset($beginn[$a->ausgabe_id])) {
                return;
            }
            $tage = (int) Carbon::parse($a->faellig_am)->startOfDay()->diffInDays(Carbon::parse($beginn[$a->ausgabe_id])->startOfDay(), false);
            DB::table('aufgaben')->where('id', $a->id)->update(['tage_vorher' => $tage, 'bezug' => 'beginn']);
        });
    }

    public function down(): void
    {
        Schema::table('aufgaben', function (Blueprint $table) {
            $table->dropColumn(['tage_vorher', 'bezug']);
        });
        Schema::table('vorlagen', function (Blueprint $table) {
            $table->dropColumn('bezug');
        });
    }
};
