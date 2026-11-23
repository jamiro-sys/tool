<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vorlage extends Model
{
    protected $table = 'vorlagen';

    public const PRIORITAETEN = ['tief' => 'Tief', 'normal' => 'Normal', 'hoch' => 'Hoch'];

    /** Wie oft im Jahr: bei jedem (gewaehlten) Turnier oder einmal pro Saison (Dezember + Februar zusammen). */
    public const HAEUFIGKEIT = ['turnier' => 'Bei jedem Turnier', 'saison' => 'Einmal pro Saison'];

    public function proSaison(): bool
    {
        return $this->haeufigkeit === 'saison';
    }

    /** Lesbare Feldnamen fuer den Verlauf. */
    public const FELDNAMEN = [
        'titel' => 'Titel', 'beschreibung' => 'Beschreibung', 'anleitung' => 'Anleitung', 'checkliste' => 'Checkliste',
        'tage_vorher' => 'Wann', 'bezug' => 'Bezug', 'haeufigkeit' => 'Wie oft', 'aufwand_std' => 'Aufwand', 'prioritaet' => 'Priorität',
        'aktiv' => 'Jedes Jahr', 'aktiv_seit' => 'Im Jahresplan seit', 'deaktiviert_am' => 'Herausgenommen am',
        'deaktiviert_grund' => 'Grund', 'ressort_id' => 'Ressort', 'turnierarten' => 'Turnierarten', 'turnier_id' => 'Turnierart',
    ];

    /** Wert eines Feldes im Verlauf lesbar machen. */
    public static function verlaufWert(string $feld, mixed $wert): string
    {
        if ($wert === null || $wert === '') {
            return 'leer';
        }

        return match ($feld) {
            'tage_vorher' => \App\Support\Wann::text((int) $wert, 'beginn'),
            'bezug' => $wert === 'ende' ? 'ab Turnierende' : 'ab Turnierbeginn',
            'haeufigkeit' => self::HAEUFIGKEIT[$wert] ?? (string) $wert,
            'aufwand_std' => \App\Support\Aufwand::text((float) $wert),
            'prioritaet' => self::PRIORITAETEN[$wert] ?? (string) $wert,
            'aktiv' => $wert ? 'Ja' : 'Nein',
            'ressort_id' => Ressort::find($wert)?->name ?? 'gelöscht',
            'turnier_id' => Turnier::find($wert)?->name ?? 'alle',
            'deaktiviert_am' => \Carbon\Carbon::parse($wert)->format('d.m.Y'),
            'checkliste' => collect(is_array($wert) ? $wert : [$wert])->map(fn ($p) => is_array($p) ? ($p['text'] ?? '') : $p)->implode(' | '),
            default => is_array($wert) ? implode(', ', $wert) : (string) $wert,
        };
    }

    protected $fillable = [
        'ressort_id', 'turnier_id', 'titel', 'beschreibung', 'anleitung', 'tage_vorher', 'bezug', 'haeufigkeit',
        'aufwand_std', 'prioritaet', 'checkliste', 'aktiv', 'aktiv_seit', 'deaktiviert_am', 'deaktiviert_grund',
    ];

    protected function casts(): array
    {
        return [
            'checkliste' => 'array',
            'aktiv' => 'boolean',
            'deaktiviert_am' => 'datetime',
            'aufwand_std' => 'float',
        ];
    }

    public function ressort(): BelongsTo
    {
        return $this->belongsTo(Ressort::class);
    }

    public function turnier(): BelongsTo
    {
        return $this->belongsTo(Turnier::class);
    }

    /** Turnierarten, fuer die die Aufgabe gilt; leer heisst: fuer alle. */
    public function turniere(): BelongsToMany
    {
        return $this->belongsToMany(Turnier::class, 'turnier_vorlage');
    }

    public function aufgaben(): HasMany
    {
        return $this->hasMany(Aufgabe::class);
    }

    public function erkenntnisse(): HasMany
    {
        return $this->hasMany(Erkenntnis::class);
    }

    public function versionen(): HasMany
    {
        return $this->hasMany(VorlageVersion::class)->latest();
    }

    public function giltFuer(Turnier|int $turnier): bool
    {
        $id = $turnier instanceof Turnier ? $turnier->id : $turnier;

        $ids = $this->turniere->pluck('id');

        return $ids->isEmpty() || $ids->contains($id);
    }

    /** Kurzname der Turnierarten, wenn die Aufgabe nicht fuer alle gilt. */
    public function turnierartText(): ?string
    {
        if ($this->proSaison()) {
            return 'einmal pro Saison';
        }

        return $this->turniere->isEmpty() ? null : $this->turniere->map(fn ($t) => $t->kurz ?: $t->name)->implode(', ');
    }

    /** Speichert die Auswahl; alle angehakt heisst: fuer alle (auch spaeter neue) Turnierarten. */
    public function setzeTurniere(?array $ids): void
    {
        $ids = collect($ids ?? [])->map(fn ($i) => (int) $i)->filter()->unique()->values();
        $alle = Turnier::pluck('id');
        $this->turniere()->sync($ids->isEmpty() || $alle->diff($ids)->isEmpty() ? [] : $ids->all());
        $this->unsetRelation('turniere');
    }

    public function faelligkeitFuer(Carbon $von, ?Carbon $bis = null): Carbon
    {
        return \App\Support\Wann::datum($von, $bis, (int) $this->tage_vorher, $this->bezug ?? 'beginn');
    }

    public function zeitpunktText(): string
    {
        return \App\Support\Wann::kurz((int) $this->tage_vorher, $this->bezug ?? 'beginn');
    }

    public function offeneErkenntnisse()
    {
        return $this->erkenntnisse()->whereNull('umgesetzt_am')->latest();
    }
}
