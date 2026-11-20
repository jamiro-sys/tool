<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumente', function (Blueprint $table) {
            $table->string('original_name')->nullable()->after('pfad');
            $table->unsignedBigInteger('groesse')->nullable()->after('original_name');
            $table->string('mime', 120)->nullable()->after('groesse');
        });
    }

    public function down(): void
    {
        Schema::table('dokumente', function (Blueprint $table) {
            $table->dropColumn(['original_name', 'groesse', 'mime']);
        });
    }
};
