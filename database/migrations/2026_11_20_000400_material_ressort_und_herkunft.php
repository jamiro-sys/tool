<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material', function (Blueprint $table) {
            $table->foreignId('ressort_id')->nullable()->after('gruppe')->constrained('ressorts')->nullOnDelete();
            $table->string('zweck', 255)->nullable()->after('ressort_id');
        });

        Schema::table('material_einsatz', function (Blueprint $table) {
            $table->string('herkunft', 20)->default('verein')->after('zurueck');
            $table->foreignId('quelle_user_id')->nullable()->after('herkunft')->constrained('users')->nullOnDelete();
            $table->string('quelle', 150)->nullable()->after('quelle_user_id');
            $table->decimal('kosten', 10, 2)->nullable()->after('quelle');
        });

        // Bisherige Ausleihe-Angaben uebernehmen
        DB::table('material_einsatz')->whereNotNull('ausleihe')->update(['quelle' => DB::raw('ausleihe'), 'herkunft' => 'geliehen']);

        Schema::table('material_einsatz', function (Blueprint $table) {
            $table->dropColumn('ausleihe');
        });
    }

    public function down(): void
    {
        Schema::table('material_einsatz', function (Blueprint $table) {
            $table->string('ausleihe', 150)->nullable();
        });
        DB::table('material_einsatz')->whereNotNull('quelle')->update(['ausleihe' => DB::raw('quelle')]);
        Schema::table('material_einsatz', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quelle_user_id');
            $table->dropColumn(['herkunft', 'quelle', 'kosten']);
        });
        Schema::table('material', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ressort_id');
            $table->dropColumn('zweck');
        });
    }
};
