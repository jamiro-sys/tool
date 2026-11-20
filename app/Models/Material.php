<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    protected $table = 'material';

    protected $fillable = ['name', 'gruppe', 'menge', 'einheit', 'standort', 'zustand', 'eigentum', 'eigentuemer', 'foto_pfad', 'notizen'];

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
}
