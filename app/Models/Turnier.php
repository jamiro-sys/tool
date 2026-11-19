<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turnier extends Model
{
    protected $table = 'turniere';

    protected $fillable = ['name', 'kurz', 'halle_id', 'beschreibung'];

    public function halle(): BelongsTo
    {
        return $this->belongsTo(Halle::class);
    }

    public function ausgaben(): HasMany
    {
        return $this->hasMany(Ausgabe::class)->orderByDesc('datum_von');
    }
}
