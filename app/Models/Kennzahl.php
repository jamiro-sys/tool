<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kennzahl extends Model
{
    protected $table = 'kennzahlen';

    protected $fillable = ['ausgabe_id', 'name', 'wert', 'notiz'];

    protected function casts(): array
    {
        return ['wert' => 'float'];
    }

    public function ausgabe(): BelongsTo
    {
        return $this->belongsTo(Ausgabe::class);
    }
}
