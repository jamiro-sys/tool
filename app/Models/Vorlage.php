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

    protected $fillable = [
        'ressort_id', 'turnier_id', 'titel', 'beschreibung', 'anleitung', 'tage_vorher',
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

    public function faelligkeitFuer(Carbon $turniertag): Carbon
    {
        return $turniertag->copy()->subDays($this->tage_vorher);
    }

    public function zeitpunktText(): string
    {
        $t = $this->tage_vorher;
        if ($t === 0) {
            return 'am Turniertag';
        }
        if ($t < 0) {
            return abs($t).(abs($t) === 1 ? ' Tag' : ' Tage').' nach dem Turnier';
        }
        if ($t % 30 === 0) {
            return ($t / 30).' '.($t === 30 ? 'Monat' : 'Monate').' vorher';
        }
        if ($t % 7 === 0 && $t >= 14) {
            return ($t / 7).' Wochen vorher';
        }

        return $t.($t === 1 ? ' Tag' : ' Tage').' vorher';
    }

    public function offeneErkenntnisse()
    {
        return $this->erkenntnisse()->whereNull('umgesetzt_am')->latest();
    }
}
