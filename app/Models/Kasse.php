<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kasse extends Model
{
    protected $table = 'kassen';

    protected $fillable = ['ausgabe_id', 'name', 'verantwortlich_id', 'start_bar', 'ende_bar', 'ende_twint', 'ende_karte', 'gezaehlt_von', 'zweite_person', 'abgeschlossen_am', 'notiz'];

    protected function casts(): array
    {
        return ['start_bar' => 'float', 'ende_bar' => 'float', 'ende_twint' => 'float', 'ende_karte' => 'float', 'abgeschlossen_am' => 'datetime'];
    }

    public function verantwortlich(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verantwortlich_id');
    }

    /** Einnahmen: Bargeld am Ende minus Wechselgeld am Start, plus TWINT und Karte. */
    public function einnahmen(): ?float
    {
        if ($this->ende_bar === null && $this->ende_twint === null && $this->ende_karte === null) {
            return null;
        }

        return ($this->ende_bar ?? 0) - ($this->start_bar ?? 0) + ($this->ende_twint ?? 0) + ($this->ende_karte ?? 0);
    }

    public function vierAugen(): bool
    {
        return filled($this->gezaehlt_von) && filled($this->zweite_person);
    }
}
