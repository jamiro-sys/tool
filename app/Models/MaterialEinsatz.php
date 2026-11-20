<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialEinsatz extends Model
{
    protected $table = 'material_einsatz';

    protected $fillable = ['ausgabe_id', 'material_id', 'menge', 'aufgebaut', 'abgebaut', 'zurueck', 'herkunft', 'quelle_user_id', 'quelle', 'kosten', 'rueckgabe_bis', 'verantwortlich_id', 'notiz'];

    /** Woher das Material fuer dieses Turnier kam. */
    public const HERKUNFT = ['verein' => 'Vom Verein', 'halle' => 'Von der Halle', 'mitgebracht' => 'Mitgebracht', 'gemietet' => 'Gemietet', 'geliehen' => 'Geliehen'];

    /** Haken, die am Turniertag abgehakt werden. */
    public const HAKEN = ['aufgebaut' => 'Aufgebaut', 'abgebaut' => 'Abgebaut', 'zurueck' => 'Versorgt'];

    protected function casts(): array
    {
        return ['menge' => 'float', 'aufgebaut' => 'boolean', 'abgebaut' => 'boolean', 'zurueck' => 'boolean', 'rueckgabe_bis' => 'date', 'erinnert_am' => 'datetime', 'kosten' => 'float'];
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

    public function quellePerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quelle_user_id');
    }

    /** Wer oder woher, als kurzer Text: "Mitgebracht von Luca", "Gemietet bei Musik Meier". */
    public function herkunftText(): string
    {
        $wer = collect([$this->quellePerson?->name, $this->quelle])->filter()->implode(', ');
        $vor = ['mitgebracht' => 'von', 'gemietet' => 'bei', 'geliehen' => 'von'][$this->herkunft] ?? null;

        $art = self::HERKUNFT[$this->herkunft] ?? $this->herkunft;

        return $art.($wer && $vor ? ' '.$vor.' '.$wer : ($wer ? ' ('.$wer.')' : ''));
    }

    public function rueckgabeUeberfaellig(): bool
    {
        return $this->rueckgabe_bis && ! $this->zurueck && $this->rueckgabe_bis->lt(today());
    }
}
