<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLLEN = [
        'admin' => 'Admin (OK-Präsidium)',
        'mitglied' => 'OK-Mitglied',
        'lesend' => 'Nur lesen (Einarbeitung)',
    ];

    /** Ereignisse, die eine Person abonnieren kann. */
    public const EREIGNISSE = [
        'zugewiesen' => 'Mir wurde eine Aufgabe zugewiesen',
        'faellig' => 'Eine meiner Aufgaben wird bald fällig',
        'ueberfaellig' => 'Eine meiner Aufgaben ist überfällig',
        'kommentar' => 'Neuer Kommentar zu meiner Aufgabe',
        'ressort' => 'Statuswechsel in meinem Ressort',
        'uebergabe' => 'Schritte einer Ressortübergabe',
        'ausgabe' => 'Neues Turnier gestartet oder Rückblick offen',
        'rundmeldung' => 'Rundmeldungen des OK',
        'woche' => 'Wochenzusammenfassung',
    ];

    public const KANAELE = ['app' => 'In der App', 'mail' => 'E-Mail', 'push' => 'Push'];

    protected $fillable = [
        'name', 'email', 'password', 'rolle', 'ansicht', 'aktiv', 'telefon',
        'einladung_token', 'eingeladen_am', 'letzte_anmeldung', 'benachrichtigungen',
        'ruhe_von', 'ruhe_bis', 'zusammenfassung', 'gesehene_version',
    ];

    protected $hidden = ['password', 'remember_token', 'einladung_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'eingeladen_am' => 'datetime',
            'letzte_anmeldung' => 'datetime',
            'password' => 'hashed',
            'aktiv' => 'boolean',
            'zusammenfassung' => 'boolean',
            'benachrichtigungen' => 'array',
        ];
    }

    public function ressorts(): BelongsToMany
    {
        return $this->belongsToMany(Ressort::class)->withPivot('rolle', 'seit')->withTimestamps();
    }

    public function aufgaben(): HasMany
    {
        return $this->hasMany(Aufgabe::class, 'verantwortlich_id');
    }

    public function pushAbos(): HasMany
    {
        return $this->hasMany(PushAbo::class);
    }

    public function istAdmin(): bool
    {
        return $this->rolle === 'admin';
    }

    public function istLesend(): bool
    {
        return $this->rolle === 'lesend';
    }

    /** Dateiablage: Allgemein fuer alle mit Schreibrecht, Ressort-Ordner fuer wer dort mitarbeitet (Admin ueberall). */
    public function darfAblegen(Ressort|int|null $ressort): bool
    {
        if ($this->istLesend()) {
            return false;
        }

        return $ressort === null || $this->istAdmin() || $this->gehoertZuRessort($ressort);
    }

    public function istExperte(): bool
    {
        return $this->ansicht === 'experte';
    }

    public function leitetRessort(Ressort|int|null $ressort): bool
    {
        if ($ressort === null) {
            return false;
        }
        $id = $ressort instanceof Ressort ? $ressort->id : $ressort;

        return $this->ressorts->contains(fn ($r) => $r->id === $id && $r->pivot->rolle === 'leitung');
    }

    public function gehoertZuRessort(Ressort|int|null $ressort): bool
    {
        if ($ressort === null) {
            return false;
        }
        $id = $ressort instanceof Ressort ? $ressort->id : $ressort;

        return $this->ressorts->contains('id', $id);
    }

    public function initialen(): string
    {
        $teile = preg_split('/\s+/', trim($this->name));

        return mb_strtoupper(mb_substr($teile[0] ?? '', 0, 1).mb_substr($teile[1] ?? '', 0, 1));
    }

    /** Liefert die gewuenschten Kanaele fuer ein Ereignis. */
    public function kanaeleFuer(string $ereignis): array
    {
        $standard = [
            'zugewiesen' => ['app', 'mail', 'push'],
            'faellig' => ['app', 'push'],
            'ueberfaellig' => ['app', 'mail', 'push'],
            'kommentar' => ['app', 'push'],
            'ressort' => ['app'],
            'uebergabe' => ['app', 'mail'],
            'ausgabe' => ['app', 'mail'],
            'rundmeldung' => ['app', 'mail', 'push'],
            'woche' => ['mail'],
        ];
        $eigene = $this->benachrichtigungen ?? [];

        return array_key_exists($ereignis, $eigene) ? (array) $eigene[$ereignis] : ($standard[$ereignis] ?? ['app']);
    }

    public function inRuhezeit(?\DateTimeInterface $zeit = null): bool
    {
        if (! $this->ruhe_von || ! $this->ruhe_bis) {
            return false;
        }
        $jetzt = ($zeit ? \Carbon\Carbon::instance($zeit) : now())->format('H:i');
        if ($this->ruhe_von <= $this->ruhe_bis) {
            return $jetzt >= $this->ruhe_von && $jetzt < $this->ruhe_bis;
        }

        return $jetzt >= $this->ruhe_von || $jetzt < $this->ruhe_bis;
    }
}
