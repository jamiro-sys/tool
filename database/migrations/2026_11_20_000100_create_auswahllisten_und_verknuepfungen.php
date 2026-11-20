<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eigene Listen des OK (Kategorien, Gruppen, Kennzahlen). Leer ab Werk.
        Schema::create('auswahllisten', function (Blueprint $table) {
            $table->id();
            $table->string('bereich', 40);
            $table->string('wert', 120);
            $table->unsignedInteger('sortierung')->default(0);
            $table->timestamps();
            $table->unique(['bereich', 'wert']);
        });

        // Links zu externen Werkzeugen (turnieragenda.ch, helfereinsatz.ch, ...)
        Schema::create('verknuepfungen', function (Blueprint $table) {
            $table->id();
            $table->morphs('bezug');
            $table->string('titel', 120);
            $table->string('url', 500);
            $table->string('werkzeug', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('kennzahlen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ausgabe_id')->constrained('ausgaben')->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('wert', 12, 2)->nullable();
            $table->string('notiz', 255)->nullable();
            $table->timestamps();
            $table->unique(['ausgabe_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kennzahlen');
        Schema::dropIfExists('verknuepfungen');
        Schema::dropIfExists('auswahllisten');
    }
};
