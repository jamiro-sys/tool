<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Erkenntnis extends Model
{
    protected $table = 'erkenntnisse';

    public const VORSCHLAEGE = [
        'beibehalten' => 'So beibehalten',
        'anpassen' => 'Im Jahresplan anpassen',
        'streichen' => 'Aufgabe streichen',
        'neu' => 'Neu in den Jahresplan',
    ];

    protected $fillable = ['ressort_id', 'ausgabe_id', 'aufgabe_id', 'vorlage_id', 'user_id', 'text', 'vorschlag', 'umgesetzt_am', 'umgesetzt_von'];

    protected function casts(): array
    {
        return ['umgesetzt_am' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ressort(): BelongsTo
    {
        return $this->belongsTo(Ressort::class);
    }

    public function ausgabe(): BelongsTo
    {
        return $this->belongsTo(Ausgabe::class);
    }

    public function aufgabe(): BelongsTo
    {
        return $this->belongsTo(Aufgabe::class);
    }

    public function vorlage(): BelongsTo
    {
        return $this->belongsTo(Vorlage::class);
    }
}
