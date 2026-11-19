<?php

namespace Tests\Feature;

use App\Models\Ressort;
use App\Models\User;
use Database\Seeders\GrundSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class OkTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(GrundSeeder::class);
    }

    protected function person(string $rolle = 'mitglied', ?string $ressort = null, string $ressortRolle = 'mitglied'): User
    {
        $u = User::create(['name' => 'Test '.uniqid(), 'email' => uniqid().'@example.ch', 'password' => 'passwort123', 'rolle' => $rolle]);
        if ($ressort) {
            $u->ressorts()->attach(Ressort::where('kuerzel', $ressort)->value('id'), ['rolle' => $ressortRolle]);
        }

        return $u->fresh();
    }
}
