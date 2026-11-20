<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EinkaufPosten extends Model
{
    protected $table = 'einkauf_posten';

    protected $fillable = ['ausgabe_id', 'produkt_id', 'bezeichnung', 'einheit', 'bestellt', 'verbraucht', 'lieferant', 'eingekauft', 'notiz'];

    protected function casts(): array
    {
        return ['bestellt' => 'float', 'verbraucht' => 'float', 'eingekauft' => 'boolean'];
    }

    public function produkt(): BelongsTo
    {
        return $this->belongsTo(Produkt::class);
    }

    /** Restmenge nach dem Turnier, falls beides erfasst ist. */
    public function rest(): ?float
    {
        return $this->bestellt !== null && $this->verbraucht !== null ? $this->bestellt - $this->verbraucht : null;
    }
}
