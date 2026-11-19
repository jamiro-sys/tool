<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kommentar extends Model
{
    protected $table = 'kommentare';

    protected $fillable = ['aufgabe_id', 'user_id', 'text'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aufgabe(): BelongsTo
    {
        return $this->belongsTo(Aufgabe::class);
    }
}
