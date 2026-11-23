<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jahresplan-Eintraege koennen bei jedem Turnier oder einmal pro Saison (Dezember + Februar zusammen) vorkommen.
 * Saison-Aufgaben entstehen nur im ersten angelegten Turnier der Saison und erscheinen im anderen mit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vorlagen', function (Blueprint $table) {
            $table->string('haeufigkeit', 8)->default('turnier')->after('bezug');
        });
        Schema::table('aufgaben', function (Blueprint $table) {
            $table->boolean('saison_aufgabe')->default(false)->after('bezug');
            $table->index('saison_aufgabe');
        });
    }

    public function down(): void
    {
        Schema::table('aufgaben', function (Blueprint $table) {
            $table->dropIndex(['saison_aufgabe']);
            $table->dropColumn('saison_aufgabe');
        });
        Schema::table('vorlagen', function (Blueprint $table) {
            $table->dropColumn('haeufigkeit');
        });
    }
};
