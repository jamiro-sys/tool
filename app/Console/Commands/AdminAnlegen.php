<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AdminAnlegen extends Command
{
    protected $signature = 'ok:admin {email} {name}';

    protected $description = 'Legt einen Admin an (oder macht eine Person zum Admin) und gibt den Einladungslink aus.';

    public function handle(): int
    {
        $user = User::firstOrNew(['email' => $this->argument('email')]);
        $user->name = $this->argument('name');
        $user->rolle = 'admin';
        $user->ansicht = 'experte';
        $user->aktiv = true;
        if (! $user->password) {
            $user->einladung_token = Str::random(48);
            $user->eingeladen_am = now();
        }
        $user->save();

        $this->info('Admin gespeichert: '.$user->email);
        if ($user->einladung_token) {
            $this->line('Einladungslink: '.route('einladung', $user->einladung_token));
        }

        return self::SUCCESS;
    }
}
