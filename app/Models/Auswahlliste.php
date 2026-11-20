<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Auswahlliste extends Model
{
    protected $table = 'auswahllisten';

    protected $fillable = ['bereich', 'wert', 'sortierung'];

    /** Bereiche, fuer die das OK eigene Eintraege anlegt. Ab Werk ohne Eintraege. */
    public const BEREICHE = [
        'kennzahl' => ['Kennzahlen', 'Was ihr pro Turnier festhalten wollt, z. B. aus turnieragenda.ch oder helfereinsatz.ch.'],
        'materialgruppe' => ['Materialgruppen', 'Gruppen für Material und Inventar.'],
        'standort' => ['Standorte', 'Wo Material gelagert wird.'],
    ];

    public static function werte(string $bereich): Collection
    {
        return static::where('bereich', $bereich)->orderBy('sortierung')->orderBy('wert')->pluck('wert');
    }
}
