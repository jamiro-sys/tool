<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Keine vorbefuellten Inhalte im echten Tool: Ressorts, Turnierarten, Hallen und Jahresplan legt das OK selbst an.
        // Beispieldaten nur fuer die lokale Entwicklung.
        if (app()->environment('local')) {
            $this->call(GrundSeeder::class);
            $this->call(DemoSeeder::class);
        }
    }
}
