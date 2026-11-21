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
        'beschreibung', 'anleitung', 'faellig_am', 'tage_vorher', 'bezug', 'status', 'prioritaet', 'aufwand_geplant',
        'aufwand_effektiv', 'checkliste', 'erledigt_am', 'erledigt_von', 'erinnert_am',
    ];

    protected function casts(): array
    {
        return [
            'faellig_am' => 'date',
            'tage_vorher' => 'integer',
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

    /** Kurz und einheitlich: "seit 3 Tagen", "heute", "morgen", "in 4 Tagen", sonst "Sa, 8. Feb." (Jahr nur wenn anders). */
    public function faelligText(): string
    {
        if (! $this->faellig_am) {
            return 'ohne Termin';
        }
        $tage = (int) today()->diffInDays($this->faellig_am, false);
        if ($this->istOffen()) {
            $relativ = match (true) {
                $tage === 0 => 'heute',
                $tage === 1 => 'morgen',
                $tage === -1 => 'seit gestern',
                $tage < 0 => 'seit '.abs($tage).' Tagen',
                $tage <= 14 => 'in '.$tage.' Tagen',
                default => null,
            };
            if ($relativ) {
                return $relativ;
            }
        }

        return $this->datumKurz();
    }

    /** Fuer Meldungen: "Fällig heute", "Fällig in 4 Tagen", "Fällig am Sa, 8. Feb", "Ohne Termin". */
    public function faelligSatz(): string
    {
        if (! $this->faellig_am) {
            return 'Ohne Termin';
        }
        $text = $this->faelligText();

        return preg_match('/^(heute|morgen|seit|in )/', $text) ? 'Fällig '.$text : 'Fällig am '.$text;
    }

    /** "Sa, 8. Feb." bzw. "Sa, 8. Feb. 2027", wenn nicht im laufenden Jahr. */
    public function datumKurz(): string
    {
        if (! $this->faellig_am) {
            return 'ohne Termin';
        }
        $d = $this->faellig_am->copy()->locale('de_CH');

        return $d->isoFormat($d->year === today()->year ? 'dd, D. MMM' : 'dd, D. MMM YYYY');
    }

    public function verknuepfungen(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Verknuepfung::class, 'bezug')->latest();
    }

    /** "14 Tage vor Turnierbeginn" bzw. leer bei fixem Datum. */
    public function wannText(): string
    {
        return \App\Support\Wann::text($this->tage_vorher, $this->bezug);
    }

    /** Datum aus dem Abstand neu berechnen (nach Aenderung des Turnierdatums). */
    public function rechneFaelligkeit(): void
    {
        if ($this->tage_vorher !== null) {
            $this->faellig_am = \App\Support\Wann::fuer($this->ausgabe, $this->tage_vorher, $this->bezug ?? 'beginn');
        }
    }
}
