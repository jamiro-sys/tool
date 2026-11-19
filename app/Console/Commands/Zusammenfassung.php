<?php

namespace App\Console\Commands;

use App\Mail\Zusammenfassung as ZusammenfassungMail;
use App\Models\Aufgabe;
use App\Models\Einstellung;
use App\Models\User;
use App\Models\Versand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class Zusammenfassung extends Command
{
    protected $signature = 'ok:zusammenfassung {art=tag : tag oder woche}';

    protected $description = 'Versendet die Tages- oder Wochenzusammenfassung per E-Mail.';

    public function handle(): int
    {
        $art = $this->argument('art') === 'woche' ? 'woche' : 'tag';
        if ($art === 'woche' && ! Einstellung::wert('funktion_wochenmail', true)) {
            return self::SUCCESS;
        }

        $personen = User::where('aktiv', true)->whereNotNull('password')->get()->filter(
            fn (User $u) => $art === 'woche' ? in_array('mail', $u->kanaeleFuer('woche'), true) : $u->zusammenfassung
        );

        $anzahl = 0;
        foreach ($personen as $u) {
            $meldungen = $art === 'tag'
                ? $u->unreadNotifications()->where('created_at', '>=', now()->subDay())->get()
                : collect();
            $aufgaben = Aufgabe::with('ressort', 'ausgabe')->offen()->where('verantwortlich_id', $u->id)
                ->whereNotNull('faellig_am')->whereDate('faellig_am', '<=', today()->addDays($art === 'woche' ? 14 : 7))
                ->whereHas('ausgabe', fn ($q) => $q->where('status', '!=', 'abgeschlossen'))
                ->orderBy('faellig_am')->get();

            if ($meldungen->isEmpty() && $aufgaben->isEmpty()) {
                continue;
            }

            try {
                Mail::to($u)->send(new ZusammenfassungMail($u, $art, $meldungen, $aufgaben));
                Versand::create(['user_id' => $u->id, 'kanal' => 'mail', 'typ' => 'zusammenfassung-'.$art, 'status' => 'ok']);
                $anzahl++;
            } catch (\Throwable $e) {
                Versand::create(['user_id' => $u->id, 'kanal' => 'mail', 'typ' => 'zusammenfassung-'.$art, 'status' => 'fehler', 'fehler' => mb_substr($e->getMessage(), 0, 240)]);
            }
        }

        $this->info("{$anzahl} Zusammenfassungen versendet.");

        return self::SUCCESS;
    }
}
