<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vorlagen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ressort_id')->constrained()->cascadeOnDelete();
            $table->foreignId('turnier_id')->nullable()->constrained('turniere')->nullOnDelete(); // null = beide Turniere
            $table->string('titel');
            $table->text('beschreibung')->nullable();
            $table->text('anleitung')->nullable();
            $table->integer('tage_vorher')->default(14); // negativ = nach dem Turnier
            $table->decimal('aufwand_std', 6, 1)->nullable();
            $table->string('prioritaet')->default('normal'); // tief, normal, hoch
            $table->json('checkliste')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->string('aktiv_seit', 16)->nullable();
            $table->timestamp('deaktiviert_am')->nullable();
            $table->string('deaktiviert_grund')->nullable();
            $table->timestamps();
        });

        Schema::create('vorlage_versionen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vorlage_id')->constrained('vorlagen')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('vorher')->nullable();
            $table->json('nachher')->nullable();
            $table->string('notiz')->nullable();
            $table->timestamps();
        });

        Schema::create('aufgaben', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ausgabe_id')->constrained('ausgaben')->cascadeOnDelete();
            $table->foreignId('vorlage_id')->nullable()->constrained('vorlagen')->nullOnDelete();
            $table->foreignId('ressort_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verantwortlich_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('begleitung_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titel');
            $table->text('beschreibung')->nullable();
            $table->text('anleitung')->nullable();
            $table->date('faellig_am')->nullable();
            $table->string('status')->default('offen'); // offen, in_arbeit, erledigt, entfaellt
            $table->string('prioritaet')->default('normal');
            $table->decimal('aufwand_geplant', 6, 1)->nullable();
            $table->decimal('aufwand_effektiv', 6, 1)->nullable();
            $table->json('checkliste')->nullable();
            $table->timestamp('erledigt_am')->nullable();
            $table->foreignId('erledigt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('erinnert_am')->nullable();
            $table->timestamps();
            $table->index(['ausgabe_id', 'status']);
            $table->index(['verantwortlich_id', 'status']);
        });

        Schema::create('kommentare', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aufgabe_id')->constrained('aufgaben')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('text');
            $table->timestamps();
        });

        Schema::create('erkenntnisse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ressort_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ausgabe_id')->nullable()->constrained('ausgaben')->nullOnDelete();
            $table->foreignId('aufgabe_id')->nullable()->constrained('aufgaben')->nullOnDelete();
            $table->foreignId('vorlage_id')->nullable()->constrained('vorlagen')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('text');
            $table->string('vorschlag')->default('anpassen'); // beibehalten, anpassen, streichen, neu
            $table->timestamp('umgesetzt_am')->nullable();
            $table->foreignId('umgesetzt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erkenntnisse');
        Schema::dropIfExists('kommentare');
        Schema::dropIfExists('aufgaben');
        Schema::dropIfExists('vorlage_versionen');
        Schema::dropIfExists('vorlagen');
    }
};
