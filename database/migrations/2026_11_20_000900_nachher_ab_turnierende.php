<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Aufgaben und Jahresplan-Eintraege "nach dem Turnier" zaehlen ab Turnierende statt ab Turnierbeginn.
 * Das Datum bestehender Aufgaben bleibt gleich; nur der Abstand wird neu ab dem letzten Turniertag ausgedrueckt.
 */
return new class extends Migration
{
    public function up(): void
    {
        \App\Support\Datenpflege::nachherAbEnde();
    }

    public function down(): void
    {
        // Datum bleibt in beide Richtungen gleich; keine Rueckrechnung noetig
    }
};
