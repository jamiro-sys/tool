<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Zwei Nachkommastellen, damit Minuten (z. B. 15 Min. = 0.25 Std.) genau gespeichert werden
    public function up(): void
    {
        Schema::table('vorlagen', fn (Blueprint $t) => $t->decimal('aufwand_std', 7, 2)->nullable()->change());
        Schema::table('aufgaben', function (Blueprint $t) {
            $t->decimal('aufwand_geplant', 7, 2)->nullable()->change();
            $t->decimal('aufwand_effektiv', 7, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vorlagen', fn (Blueprint $t) => $t->decimal('aufwand_std', 6, 1)->nullable()->change());
        Schema::table('aufgaben', function (Blueprint $t) {
            $t->decimal('aufwand_geplant', 6, 1)->nullable()->change();
            $t->decimal('aufwand_effektiv', 6, 1)->nullable()->change();
        });
    }
};
