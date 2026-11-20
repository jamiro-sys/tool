<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unterordner in der Dateiablage, je Ressort (ohne Ressort = Allgemein)
        Schema::create('ordner', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ressort_id')->nullable()->constrained('ressorts')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('sortierung')->default(0);
            $table->timestamps();
            $table->unique(['ressort_id', 'name']);
        });
        Schema::table('dokumente', function (Blueprint $table) {
            $table->foreignId('ordner_id')->nullable()->after('ressort_id')->constrained('ordner')->nullOnDelete();
        });

        // Jahresplan: eine Aufgabe kann fuer mehrere Turnierarten gelten (keine = alle)
        Schema::create('turnier_vorlage', function (Blueprint $table) {
            $table->foreignId('vorlage_id')->constrained('vorlagen')->cascadeOnDelete();
            $table->foreignId('turnier_id')->constrained('turniere')->cascadeOnDelete();
            $table->primary(['vorlage_id', 'turnier_id']);
        });
        DB::table('vorlagen')->whereNotNull('turnier_id')->orderBy('id')->each(function ($v) {
            DB::table('turnier_vorlage')->insert(['vorlage_id' => $v->id, 'turnier_id' => $v->turnier_id]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnier_vorlage');
        Schema::table('dokumente', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ordner_id');
        });
        Schema::dropIfExists('ordner');
    }
};
