<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Aktivitaet extends Model
{
    protected $table = 'aktivitaeten';

    protected $fillable = ['user_id', 'aktion', 'subjekt_type', 'subjekt_id', 'beschreibung', 'daten', 'ip'];

    /** Lesbare Namen der Bereiche (erster Teil von "aktion"). */
    public const BEREICHE = [
        'anmeldung' => 'Anmeldung', 'assistent' => 'Claude-Assistent', 'aufgabe' => 'Aufgaben', 'ausgabe' => 'Turniere',
        'dateien' => 'Dateien', 'daten' => 'Daten', 'einladung' => 'Einladungen', 'einstellungen' => 'Einstellungen',
        'erkenntnis' => 'Rückblick', 'link' => 'Links', 'person' => 'Personen', 'ressort' => 'Ressorts',
        'sicherung' => 'Sicherungen', 'system' => 'System', 'uebergabe' => 'Übergaben', 'vorlage' => 'Jahresplan',
    ];

    public static function bereich(string $aktion): string
    {
        $k = strtok($aktion, '.');

        return self::BEREICHE[$k] ?? ucfirst($k);
    }

    protected function casts(): array
    {
        return ['daten' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjekt(): MorphTo
    {
        return $this->morphTo();
    }

    public static function protokolliere(string $aktion, string $beschreibung, ?Model $subjekt = null, array $daten = []): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'aktion' => $aktion,
            'subjekt_type' => $subjekt ? $subjekt->getMorphClass() : null,
            'subjekt_id' => $subjekt?->getKey(),
            'beschreibung' => mb_substr($beschreibung, 0, 250),
            'daten' => $daten ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
