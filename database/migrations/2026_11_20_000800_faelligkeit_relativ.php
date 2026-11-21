<?php

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
        \App\Support\Datenpflege::faelligkeitRelativ();
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
