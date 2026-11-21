<?php

namespace App\Http\Controllers\Admin;

use App\Channels\WebPushKanal;
use App\Http\Controllers\Controller;
use App\Models\Aktivitaet;
use App\Models\Deployment;
use App\Models\Einstellung;
use App\Models\Versand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SystemController extends Controller
{
    public function index()
    {
        $cron = Einstellung::wert('cron_letzter_lauf');
        $logDatei = storage_path('logs/laravel.log');

        return view('admin.system', [
            'claudeBereit' => app(\App\Services\AufgabenAssistent::class)->bereit(),
            'info' => [
                'Version' => config('app.version'),
                'Umgebung' => app()->environment(),
                'Debug' => config('app.debug') ? 'an' : 'aus',
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                'Datenbank' => DB::connection()->getDriverName().' '.$this->dbVersion(),
                'Mail' => config('mail.default').' ('.config('mail.mailers.smtp.host').')',
                'Push' => WebPushKanal::konfiguriert() ? 'eingerichtet' : 'Schlüssel fehlen oder sind ungültig: unten «Push-Schlüssel erzeugen»',
                'Speicher frei' => $this->freierSpeicher(),
                'Zeitzone' => config('app.timezone'),
            ],
            'cron' => $cron ? \Carbon\Carbon::parse($cron) : null,
            'jobs' => DB::table('jobs')->count(),
            'aeltesterJob' => DB::table('jobs')->min('created_at'),
            'fehlgeschlagen' => DB::table('failed_jobs')->latest('failed_at')->limit(20)->get(),
            'deployments' => Deployment::latest()->limit(10)->get(),
            'wartung' => app()->isDownForMaintenance(),
            'versand' => Versand::with('user')->latest()->limit(30)->get(),
            'versandFehler' => Versand::where('status', 'fehler')->where('created_at', '>=', now()->subDays(7))->count(),
            'logGroesse' => is_file($logDatei) ? filesize($logDatei) : 0,
        ]);
    }

    public function logs(Request $request)
    {
        $datei = storage_path('logs/laravel.log');
        $stufe = $request->query('stufe');
        $zeilen = [];
        if (is_file($datei)) {
            $inhalt = $this->ende($datei, 400 * 1024);
            preg_match_all('/^\[(\d{4}-\d{2}-\d{2}[ T][\d:.+\-]+)\] (\w+)\.(\w+): (.*?)(?=^\[\d{4}-\d{2}-\d{2}|\z)/ms', $inhalt, $treffer, PREG_SET_ORDER);
            foreach (array_reverse($treffer) as $t) {
                if ($stufe && strtolower($t[3]) !== $stufe) {
                    continue;
                }
                $text = trim($t[4]);
                $zeilen[] = ['zeit' => $t[1], 'stufe' => strtolower($t[3]), 'titel' => Str::limit(strtok($text, "\n"), 300), 'detail' => Str::limit($text, 4000)];
                if (count($zeilen) >= 200) {
                    break;
                }
            }
        }

        return view('admin.logs', ['zeilen' => $zeilen, 'stufe' => $stufe]);
    }

    public function logsLeeren()
    {
        $datei = storage_path('logs/laravel.log');
        if (is_file($datei)) {
            file_put_contents($datei, '');
        }
        Aktivitaet::protokolliere('system.logs', 'Log geleert');

        return back()->with('erfolg', 'Log geleert.');
    }

    public function aktion(Request $request)
    {
        $aktion = $request->validate(['aktion' => ['required', 'in:cache,optimieren,queue,wartung_an,wartung_aus,migrieren,speicher_link']])['aktion'];

        $meldung = match ($aktion) {
            'cache' => tap('Cache geleert.', fn () => Artisan::call('optimize:clear')),
            'optimieren' => tap('Routen und Ereignisse zwischengespeichert.', fn () => Artisan::call('route:cache') + Artisan::call('event:cache')),
            'queue' => tap('Warteschlange abgearbeitet.', fn () => Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 40, '--tries' => 3])),
            'migrieren' => tap('Sicherung erstellt, Datenbank auf dem neusten Stand.', function () {
                app(\App\Services\Sicherung::class)->erstelle('vor-migration');
                Artisan::call('migrate', ['--force' => true]);
            }),
            'speicher_link' => tap('Speicher-Link erstellt.', fn () => Artisan::call('storage:link', ['--relative' => true])),
            'wartung_an' => $this->wartungAn(),
            'wartung_aus' => tap('Wartungsmodus beendet.', fn () => Artisan::call('up')),
        };

        Aktivitaet::protokolliere('system.'.$aktion, $meldung);

        return back()->with('erfolg', $meldung)->with('ausgabe', trim(Artisan::output()));
    }

    public function jobWiederholen(Request $request)
    {
        $id = $request->input('id', 'all');
        Artisan::call('queue:retry', ['id' => [$id]]);

        return back()->with('erfolg', 'Job(s) erneut eingereiht.');
    }

    public function jobsLoeschen()
    {
        Artisan::call('queue:flush');

        return back()->with('erfolg', 'Fehlgeschlagene Jobs gelöscht.');
    }

    public function mailTest(Request $request)
    {
        try {
            Mail::raw('Testmail aus dem OK-Tool. Der Mailversand funktioniert.', fn ($m) => $m->to($request->user()->email)->subject('Testmail OK-Tool'));
            Versand::create(['user_id' => $request->user()->id, 'kanal' => 'mail', 'typ' => 'test', 'status' => 'ok']);

            return back()->with('erfolg', 'Testmail an '.$request->user()->email.' gesendet.');
        } catch (\Throwable $e) {
            Versand::create(['user_id' => $request->user()->id, 'kanal' => 'mail', 'typ' => 'test', 'status' => 'fehler', 'fehler' => Str::limit($e->getMessage(), 240)]);

            return back()->withErrors(['mail' => 'Mailversand fehlgeschlagen: '.$e->getMessage()]);
        }
    }

    /** Push-Schluessel erzeugen und direkt in die .env schreiben (Fallback: anzeigen). */
    public function vapid()
    {
        $schluessel = \Minishlink\WebPush\VAPID::createVapidKeys();
        $datei = base_path('.env');
        $inhalt = is_file($datei) ? (string) file_get_contents($datei) : '';
        foreach (['VAPID_PUBLIC_KEY' => $schluessel['publicKey'], 'VAPID_PRIVATE_KEY' => $schluessel['privateKey']] as $name => $wert) {
            $zeile = $name.'='.$wert;
            $inhalt = preg_match('/^'.$name.'=.*$/m', $inhalt)
                ? preg_replace_callback('/^'.$name.'=.*$/m', fn () => $zeile, $inhalt)
                : rtrim($inhalt)."\n".$zeile."\n";
        }
        if (is_file($datei) && is_writable($datei) && file_put_contents($datei, $inhalt) !== false) {
            \App\Models\PushAbo::query()->delete();
            Aktivitaet::protokolliere('system.vapid', 'Neue Push-Schlüssel erzeugt');

            return back()->with('erfolg', 'Push ist eingerichtet. Jede Person aktiviert Push jetzt unter Benachrichtigungen auf ihrem Gerät.');
        }

        return back()->with('vapid', $schluessel)->with('warnung', 'Die Konfiguration konnte nicht automatisch gespeichert werden. Bitte die Werte von Hand eintragen.');
    }


    protected function wartungAn(): string
    {
        $geheim = Str::random(24);
        Artisan::call('down', ['--secret' => $geheim, '--retry' => 60]);
        session()->flash('wartung_link', url('/'.$geheim));

        return 'Wartungsmodus aktiv. Mit dem Link unten kommst du weiterhin ins Tool.';
    }

    protected function dbVersion(): string
    {
        try {
            return DB::connection()->getDriverName() === 'sqlite'
                ? (string) DB::selectOne('select sqlite_version() as v')->v
                : (string) DB::selectOne('select version() as v')->v;
        } catch (\Throwable) {
            return '';
        }
    }

    protected function freierSpeicher(): string
    {
        $frei = @disk_free_space(storage_path());

        return $frei ? round($frei / 1024 / 1024 / 1024, 1).' GB' : 'unbekannt';
    }

    protected function ende(string $datei, int $bytes): string
    {
        $groesse = filesize($datei);
        $h = fopen($datei, 'r');
        fseek($h, max(0, $groesse - $bytes));
        $inhalt = stream_get_contents($h);
        fclose($h);

        return $inhalt;
    }
}
