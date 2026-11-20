<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produkte', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('kategorie', 120)->nullable();
            $table->decimal('preis', 8, 2)->nullable();
            $table->string('einheit', 40)->nullable();
            $table->json('allergene')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->unsignedInteger('sortierung')->default(0);
            $table->text('notizen')->nullable();
            $table->timestamps();
        });

        Schema::create('einkauf_posten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ausgabe_id')->constrained('ausgaben')->cascadeOnDelete();
            $table->foreignId('produkt_id')->nullable()->constrained('produkte')->nullOnDelete();
            $table->string('bezeichnung', 150);
            $table->string('einheit', 40)->nullable();
            $table->decimal('bestellt', 10, 2)->nullable();
            $table->decimal('verbraucht', 10, 2)->nullable();
            $table->string('lieferant', 150)->nullable();
            $table->boolean('eingekauft')->default(false);
            $table->string('notiz', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('kassen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ausgabe_id')->constrained('ausgaben')->cascadeOnDelete();
            $table->string('name', 120);
            $table->foreignId('verantwortlich_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('start_bar', 10, 2)->nullable();
            $table->decimal('ende_bar', 10, 2)->nullable();
            $table->decimal('ende_twint', 10, 2)->nullable();
            $table->decimal('ende_karte', 10, 2)->nullable();
            $table->string('gezaehlt_von', 120)->nullable();
            $table->string('zweite_person', 120)->nullable();
            $table->timestamp('abgeschlossen_am')->nullable();
            $table->text('notiz')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kassen');
        Schema::dropIfExists('einkauf_posten');
        Schema::dropIfExists('produkte');
    }
};
