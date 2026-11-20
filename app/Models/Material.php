<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    protected $table = 'material';

    protected $fillable = ['name', 'gruppe', 'ressort_id', 'zweck', 'menge', 'einheit', 'standort', 'zustand', 'eigentum', 'eigentuemer', 'foto_pfad', 'notizen'];

    public const ZUSTAND = ['gut' => 'In Ordnung', 'reparieren' => 'Reparieren', 'defekt' => 'Defekt'];

    public const EIGENTUM = ['verein' => 'Verein', 'halle' => 'Gehört der Halle', 'geliehen' => 'Geliehen'];

    protected function casts(): array
    {
        return ['menge' => 'float'];
    }

    public function einsaetze(): HasMany
    {
        return $this->hasMany(MaterialEinsatz::class);
    }

    public function ressort(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Ressort::class);
    }

    /** Wie oft gemietet, Summe der Kosten und ob sich ein Kauf lohnen koennte. Erwartet geladene einsaetze. */
    public function mietBilanz(): array
    {
        $miete = $this->einsaetze->where('herkunft', 'gemietet');
        $schwelle = (int) Einstellung::wert('anschaffung_ab', 3);

        return [
            'anzahl' => $miete->count(),
            'kosten' => (float) $miete->sum('kosten'),
            'pruefen' => $schwelle > 0 && $miete->count() >= $schwelle,
        ];
    }
}
