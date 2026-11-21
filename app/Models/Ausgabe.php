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

    /** Das Turnier, um das es gerade geht: das laufende oder naechste; sonst das zuletzt vergangene, das noch nicht abgeschlossen ist. */
    public static function naechste(): ?self
    {
        $offen = static::whereIn('status', ['planung', 'laufend', 'rueckblick'])->orderBy('datum_von')->get();

        return $offen->first(fn (self $a) => ($a->datum_bis ?? $a->datum_von)->gte(today()))
            ?? $offen->sortByDesc('datum_von')->first();
    }

    /** Erinnerungen nur bis 30 Tage nach Turnierende (danach geht es um den Rueckblick). */
    public function scopeNochAktuell(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        $grenze = today()->subDays(30)->toDateString();

        return $q->where('status', '!=', 'abgeschlossen')
            ->where(fn ($w) => $w->whereDate('datum_bis', '>=', $grenze)->orWhere(fn ($x) => $x->whereNull('datum_bis')->whereDate('datum_von', '>=', $grenze)));
    }

    public function verknuepfungen(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Verknuepfung::class, 'bezug')->latest();
    }

    public function kennzahlen(): HasMany
    {
        return $this->hasMany(Kennzahl::class);
    }

    public function material(): HasMany
    {
        return $this->hasMany(MaterialEinsatz::class);
    }

    /** Letzte fruehere Ausgabe desselben Turniers, als Vergleich und Vorlage. */
    public function vorherige(): ?self
    {
        return static::where('turnier_id', $this->turnier_id)->where('datum_von', '<', $this->datum_von)->orderByDesc('datum_von')->first();
    }
}
