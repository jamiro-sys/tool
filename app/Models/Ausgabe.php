<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ausgabe extends Model
{
    protected $table = 'ausgaben';

    public const STATUS = [
        'planung' => 'In Planung',
        'laufend' => 'Turnierwoche',
        'rueckblick' => 'Rückblick',
        'abgeschlossen' => 'Abgeschlossen',
    ];

    protected $fillable = ['turnier_id', 'halle_id', 'saison', 'titel', 'datum_von', 'datum_bis', 'status', 'notizen'];

    protected function casts(): array
    {
        return ['datum_von' => 'date', 'datum_bis' => 'date'];
    }

    public function turnier(): BelongsTo
    {
        return $this->belongsTo(Turnier::class);
    }

    public function halle(): BelongsTo
    {
        return $this->belongsTo(Halle::class);
    }

    public function aufgaben(): HasMany
    {
        return $this->hasMany(Aufgabe::class);
    }

    public function erkenntnisse(): HasMany
    {
        return $this->hasMany(Erkenntnis::class);
    }

    public function istAktiv(): bool
    {
        return in_array($this->status, ['planung', 'laufend', 'rueckblick'], true);
    }

    public function tageBisTurnier(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->datum_von->copy()->startOfDay(), false);
    }

    public function datumText(): string
    {
        $von = $this->datum_von;
        $bis = $this->datum_bis;
        if ($bis && ! $bis->isSameDay($von)) {
            if ($von->month === $bis->month) {
                return $von->format('j.').'/'.$bis->locale('de_CH')->isoFormat('D. MMMM YYYY');
            }

            return $von->locale('de_CH')->isoFormat('D. MMMM').' bis '.$bis->locale('de_CH')->isoFormat('D. MMMM YYYY');
        }

        return $von->locale('de_CH')->isoFormat('D. MMMM YYYY');
    }

    /** Fortschritt ohne entfallene Aufgaben. */
    public function fortschritt(): array
    {
        $aufgaben = $this->relationLoaded('aufgaben') ? $this->aufgaben : $this->aufgaben()->get(['id', 'status']);
        $relevant = $aufgaben->where('status', '!=', 'entfaellt');
        $total = $relevant->count();
        $erledigt = $relevant->where('status', 'erledigt')->count();

        return [
            'total' => $total,
            'erledigt' => $erledigt,
            'prozent' => $total ? (int) round($erledigt / $total * 100) : 0,
        ];
    }

    public static function naechste(): ?self
    {
        return static::whereIn('status', ['planung', 'laufend', 'rueckblick'])
            ->orderBy('datum_von')
            ->first();
    }
}
