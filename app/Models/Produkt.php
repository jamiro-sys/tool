<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produkt extends Model
{
    protected $table = 'produkte';

    protected $fillable = ['name', 'kategorie', 'preis', 'einheit', 'allergene', 'aktiv', 'sortierung', 'notizen'];

    protected function casts(): array
    {
        return ['allergene' => 'array', 'aktiv' => 'boolean', 'preis' => 'float'];
    }
}
