<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kontakt extends Model
{
    protected $table = 'kontakte';

    protected $fillable = ['ressort_id', 'name', 'firma', 'funktion', 'telefon', 'email', 'notizen'];

    public function ressort(): BelongsTo
    {
        return $this->belongsTo(Ressort::class);
    }
}
