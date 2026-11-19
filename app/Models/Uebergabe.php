<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Uebergabe extends Model
{
    protected $table = 'uebergaben';

    public const STATUS = [
        'geplant' => 'Geplant',
        'in_arbeit' => 'In Arbeit',
        'abgeschlossen' => 'Abgeschlossen',
        'abgebrochen' => 'Abgebrochen',
    ];

    public const STANDARD_CHECKLISTE = [
        'Ressort-Steckbrief gemeinsam durchgehen und aktualisieren',
        'Alle Vorlagen haben eine verständliche Anleitung',
        'Offene Aufgaben der laufenden Ausgabe neu zuteilen',
        'Kontakte informieren und neue Ansprechperson vorstellen',
        'Zugänge, Schlüssel und Unterlagen übergeben',
        'Erkenntnisse der letzten Ausgaben besprechen',
        'Eine Aufgabe gemeinsam erledigen (Begleitung)',
        'Übergabegespräch durchgeführt',
    ];

    protected $fillable = ['ressort_id', 'von_id', 'an_id', 'status', 'termin', 'checkliste', 'notiz', 'bestaetigt_von_am', 'bestaetigt_an_am', 'abgeschlossen_am'];

    protected function casts(): array
    {
        return [
            'termin' => 'date',
            'checkliste' => 'array',
            'bestaetigt_von_am' => 'datetime',
            'bestaetigt_an_am' => 'datetime',
            'abgeschlossen_am' => 'datetime',
        ];
    }

    public function ressort(): BelongsTo
    {
        return $this->belongsTo(Ressort::class);
    }

    public function von(): BelongsTo
    {
        return $this->belongsTo(User::class, 'von_id');
    }

    public function an(): BelongsTo
    {
        return $this->belongsTo(User::class, 'an_id');
    }

    public function fortschritt(): int
    {
        $liste = collect($this->checkliste ?? []);
        if ($liste->isEmpty()) {
            return 0;
        }

        return (int) round($liste->where('erledigt', true)->count() / $liste->count() * 100);
    }

    public function checklisteKomplett(): bool
    {
        return collect($this->checkliste ?? [])->every(fn ($p) => ! empty($p['erledigt']));
    }
}
