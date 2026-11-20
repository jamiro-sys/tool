<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Einstellung extends Model
{
    protected $table = 'einstellungen';

    protected $primaryKey = 'schluessel';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['schluessel', 'wert'];

    protected function casts(): array
    {
        return ['wert' => 'json'];
    }

    public const STANDARD = [
        'verein_name' => 'FC Münsterlingen',
        'tool_name' => 'Raiffeisen-Hallencup OK',
        'erinnerung_tage' => 3,
        'backup_aufbewahrung' => 14,
        'funktion_push' => true,
        'funktion_wochenmail' => true,
        'wochenmail_tag' => 1,
        'anschaffung_ab' => 3,
    ];

    public static function wert(string $schluessel, mixed $standard = null): mixed
    {
        $alle = Cache::rememberForever('einstellungen', fn () => static::query()->pluck('wert', 'schluessel')->all());

        return $alle[$schluessel] ?? $standard ?? (self::STANDARD[$schluessel] ?? null);
    }

    /** Wie wert(), aber ohne Fehler, wenn die Datenbank (noch) nicht bereit ist. */
    public static function wertSicher(string $schluessel, mixed $standard = null): mixed
    {
        try {
            return static::wert($schluessel, $standard);
        } catch (\Throwable) {
            return $standard ?? (self::STANDARD[$schluessel] ?? null);
        }
    }

    public static function setze(string $schluessel, mixed $wert): void
    {
        static::updateOrCreate(['schluessel' => $schluessel], ['wert' => $wert]);
        Cache::forget('einstellungen');
    }
}
