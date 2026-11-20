<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialEinsatz extends Model
{
    protected $table = 'material_einsatz';

    protected $fillable = ['ausgabe_id', 'material_id', 'menge', 'aufgebaut', 'abgebaut', 'zurueck', 'ausleihe', 'rueckgabe_bis', 'verantwortlich_id', 'notiz'];

    /** Haken, die am Turniertag abgehakt werden. */
    public const HAKEN = ['aufgebaut' => 'Aufgebaut', 'abgebaut' => 'Abgebaut', 'zurueck' => 'Versorgt'];

    protected function casts(): array
    {
        return ['menge' => 'float', 'aufgebaut' => 'boolean', 'abgebaut' => 'boolean', 'zurueck' => 'boolean', 'rueckgabe_bis' => 'date', 'erinnert_am' => 'datetime'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function ausgabe(): BelongsTo
    {
        return $this->belongsTo(Ausgabe::class);
    }

    public function verantwortlich(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verantwortlich_id');
    }

    public function rueckgabeUeberfaellig(): bool
    {
        return $this->rueckgabe_bis && ! $this->zurueck && $this->rueckgabe_bis->lt(today());
    }
}
