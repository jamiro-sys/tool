<?php

use App\Models\Einstellung;
use Illuminate\Support\Facades\Schedule;

/*
| Auf dem Webhosting laeuft kein Dauerprozess. In Plesk wird darum ein Cronjob
| eingerichtet, der jede Minute "php artisan schedule:run" startet.
*/

// Lebenszeichen fuer die Systemseite
Schedule::call(fn () => Einstellung::setze('cron_letzter_lauf', now()->toIso8601String()))
    ->everyMinute()->name('lebenszeichen');

// Warteschlange (Mails, Push) abarbeiten
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()->withoutOverlapping(5);

Schedule::command('ok:status')->hourly();
Schedule::command('ok:erinnerungen')->dailyAt('07:15');
Schedule::command('ok:zusammenfassung tag')->dailyAt('18:00');
Schedule::command('ok:zusammenfassung woche')->weeklyOn((int) (Einstellung::wertSicher('wochenmail_tag', 1)) % 7, '07:30');
Schedule::command('ok:sicherung automatisch')->dailyAt('02:30');
Schedule::command('queue:prune-failed --hours=720')->weekly();
Schedule::command('model:prune')->daily();
