<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('gruppe', 120)->nullable();
            $table->decimal('menge', 10, 2)->default(1);
            $table->string('einheit', 40)->nullable();
            $table->string('standort', 120)->nullable();
            $table->string('zustand', 20)->default('gut');
            $table->string('eigentum', 20)->default('verein');
            $table->string('eigentuemer', 150)->nullable();
            $table->string('foto_pfad')->nullable();
            $table->text('notizen')->nullable();
            $table->timestamps();
        });

        Schema::create('material_einsatz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ausgabe_id')->constrained('ausgaben')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('material')->cascadeOnDelete();
            $table->decimal('menge', 10, 2)->default(1);
            $table->boolean('aufgebaut')->default(false);
            $table->boolean('abgebaut')->default(false);
            $table->boolean('zurueck')->default(false);
            $table->string('ausleihe', 150)->nullable();
            $table->date('rueckgabe_bis')->nullable();
            $table->foreignId('verantwortlich_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('erinnert_am')->nullable();
            $table->string('notiz', 255)->nullable();
            $table->timestamps();
            $table->unique(['ausgabe_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_einsatz');
        Schema::dropIfExists('material');
    }
};
