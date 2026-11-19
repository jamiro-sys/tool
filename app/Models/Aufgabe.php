<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aufgabe extends Model
{
    protected $table = 'aufgaben';

    public const STATUS = [
        'offen' => 'Offen',
        'in_arbeit' => 'In Arbeit',
        'erledigt' => 'Erledigt',
        'entfaellt' => 'Entfällt',
    ];

    protected $fillable = [
        'ausgabe_id', 'vorlage_id', 'ressort_id', 'verantwortlich_id', 'begleitung_id', 'titel',
        'beschreibung', 'anleitung', 'faellig_am', 'status', 'prioritaet', 'aufwand_geplant',
        'aufwand_effektiv', 'checkliste', 'erledigt_am', 'erledigt_von', 'erinnert_am',
    ];

    protected function casts(): array
    {
        return [
            'faellig_am' => 'date',
            'erledigt_am' => 'datetime',
            'erinnert_am' => 'datetime',
            'checkliste' => 'array',
            'aufwand_geplant' => 'float',
            'aufwand_effektiv' => 'float',
        ];
    }

    public function ausgabe(): BelongsTo
    {
        return $this->belongsTo(Ausgabe::class);
    }

    public function vorlage(): BelongsTo
    {
        return $this->belongsTo(Vorlage::class);
    }

    public function ressort(): BelongsTo
    {
        return $this->belongsTo(Ressort::class);
    }

    public function verantwortlich(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verantwortlich_id');
    }

    public function begleitung(): BelongsTo
    {
        return $this->belongsTo(User::class, 'begleitung_id');
    }

    public function kommentare(): HasMany
    {
        return $this->hasMany(Kommentar::class)->oldest();
    }

    public function erkenntnisse(): HasMany
    {
        return $this->hasMany(Erkenntnis::class);
    }

    public function dokumente(): HasMany
    {
        return $this->hasMany(Dokument::class);
    }

    public function scopeOffen(Builder $q): Builder
    {
        return $q->whereIn('status', ['offen', 'in_arbeit']);
    }

    public function istOffen(): bool
    {
        return in_array($this->status, ['offen', 'in_arbeit'], true);
    }

    public function istUeberfaellig(): bool
    {
        return $this->istOffen() && $this->faellig_am && $this->faellig_am->lt(today());
    }

    public function istHeuteFaellig(): bool
    {
        return $this->istOffen() && $this->faellig_am && $this->faellig_am->isToday();
    }

    public function checklisteFortschritt(): ?array
    {
        $liste = $this->checkliste ?? [];
        if (! count($liste)) {
            return null;
        }
        $erledigt = collect($liste)->where('erledigt', true)->count();

        return ['erledigt' => $erledigt, 'total' => count($liste)];
    }

    public function faelligText(): string
    {
        if (! $this->faellig_am) {
            return 'ohne Termin';
        }
        if (! $this->istOffen()) {
            return $this->faellig_am->format('d.m.Y');
        }
        $tage = (int) today()->diffInDays($this->faellig_am, false);

        return match (true) {
            $tage === 0 => 'heute',
            $tage === 1 => 'morgen',
            $tage === -1 => 'seit gestern',
            $tage < 0 => 'seit '.abs($tage).' Tagen',
            $tage <= 14 => 'in '.$tage.' Tagen',
            default => $this->faellig_am->format('d.m.Y'),
        };
    }
}
