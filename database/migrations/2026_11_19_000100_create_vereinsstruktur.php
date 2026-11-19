<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ressorts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kuerzel', 8);
            $table->text('auftrag')->nullable();
            $table->unsignedInteger('sortierung')->default(0);
            $table->timestamps();
        });

        Schema::create('ressort_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ressort_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rolle')->default('mitglied'); // leitung, mitglied, nachfolge
            $table->date('seit')->nullable();
            $table->timestamps();
            $table->unique(['ressort_id', 'user_id']);
        });

        Schema::create('hallen', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ort')->nullable();
            $table->string('adresse')->nullable();
            $table->string('hallenwart')->nullable();
            $table->string('telefon')->nullable();
            $table->text('notizen')->nullable();
            $table->timestamps();
        });

        Schema::create('turniere', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kurz', 12);
            $table->foreignId('halle_id')->nullable()->constrained('hallen')->nullOnDelete();
            $table->text('beschreibung')->nullable();
            $table->timestamps();
        });

        Schema::create('ausgaben', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turnier_id')->constrained('turniere')->cascadeOnDelete();
            $table->foreignId('halle_id')->nullable()->constrained('hallen')->nullOnDelete();
            $table->string('saison', 16);
            $table->string('titel');
            $table->date('datum_von');
            $table->date('datum_bis')->nullable();
            $table->string('status')->default('planung'); // planung, laufend, rueckblick, abgeschlossen
            $table->text('notizen')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ausgaben');
        Schema::dropIfExists('turniere');
        Schema::dropIfExists('hallen');
        Schema::dropIfExists('ressort_user');
        Schema::dropIfExists('ressorts');
    }
};
