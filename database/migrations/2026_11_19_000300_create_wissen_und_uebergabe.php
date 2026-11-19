<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kontakte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ressort_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('firma')->nullable();
            $table->string('funktion')->nullable();
            $table->string('telefon')->nullable();
            $table->string('email')->nullable();
            $table->text('notizen')->nullable();
            $table->timestamps();
        });

        Schema::create('dokumente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ressort_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('aufgabe_id')->nullable()->constrained('aufgaben')->nullOnDelete();
            $table->foreignId('vorlage_id')->nullable()->constrained('vorlagen')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('titel');
            $table->string('typ')->default('link'); // link, datei
            $table->string('pfad')->nullable();
            $table->string('url', 1024)->nullable();
            $table->timestamps();
        });

        Schema::create('wissen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ressort_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('titel');
            $table->text('inhalt');
            $table->unsignedInteger('sortierung')->default(0);
            $table->timestamps();
        });

        Schema::create('uebergaben', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ressort_id')->constrained()->cascadeOnDelete();
            $table->foreignId('von_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('an_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('geplant'); // geplant, in_arbeit, abgeschlossen, abgebrochen
            $table->date('termin')->nullable();
            $table->json('checkliste')->nullable();
            $table->text('notiz')->nullable();
            $table->timestamp('bestaetigt_von_am')->nullable();
            $table->timestamp('bestaetigt_an_am')->nullable();
            $table->timestamp('abgeschlossen_am')->nullable();
            $table->timestamps();
        });

        Schema::create('aktivitaeten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('aktion', 40);
            $table->nullableMorphs('subjekt');
            $table->string('beschreibung');
            $table->json('daten')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('einstellungen', function (Blueprint $table) {
            $table->string('schluessel')->primary();
            $table->json('wert')->nullable();
            $table->timestamps();
        });

        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->string('version', 40);
            $table->string('commit', 64)->nullable();
            $table->text('aenderungen')->nullable();
            $table->string('status')->default('ok');
            $table->timestamps();
        });

        Schema::create('push_abos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 500)->unique();
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('geraet')->nullable();
            $table->timestamps();
        });

        Schema::create('rundmeldungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ressort_id')->nullable()->constrained()->nullOnDelete();
            $table->string('titel');
            $table->text('text');
            $table->unsignedInteger('empfaenger')->default(0);
            $table->timestamps();
        });

        Schema::create('versandprotokoll', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kanal', 20);
            $table->string('typ', 60);
            $table->string('status', 20)->default('ok');
            $table->string('fehler')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'versandprotokoll', 'rundmeldungen', 'push_abos', 'deployments', 'einstellungen', 'aktivitaeten', 'uebergaben', 'wissen', 'dokumente', 'kontakte'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
