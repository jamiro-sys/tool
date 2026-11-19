<?php

namespace App\Console\Commands;

use App\Models\Deployment;
use Illuminate\Console\Command;

class DeployRegistrieren extends Command
{
    protected $signature = 'ok:deploy {version} {--commit=} {--aenderungen=}';

    protected $description = 'Hält ein Update im Deploy-Verlauf fest (wird von der Pipeline aufgerufen).';

    public function handle(): int
    {
        Deployment::create([
            'version' => mb_substr($this->argument('version'), 0, 40),
            'commit' => $this->option('commit'),
            'aenderungen' => $this->option('aenderungen'),
        ]);
        $this->info('Deploy registriert.');

        return self::SUCCESS;
    }
}
