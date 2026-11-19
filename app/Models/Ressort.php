<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ressort extends Model
{
    protected $fillable = ['name', 'kuerzel', 'auftrag', 'sortierung'];

    public function mitglieder(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('rolle', 'seit')->withTimestamps();
    }

    public function leitung(): ?User
    {
        return $this->mitglieder->firstWhere('pivot.rolle', 'leitung');
    }

    public function nachfolge(): ?User
    {
        return $this->mitglieder->firstWhere('pivot.rolle', 'nachfolge');
    }

    public function vorlagen(): HasMany
    {
        return $this->hasMany(Vorlage::class);
    }

    public function aufgaben(): HasMany
    {
        return $this->hasMany(Aufgabe::class);
    }

    public function kontakte(): HasMany
    {
        return $this->hasMany(Kontakt::class);
    }

    public function dokumente(): HasMany
    {
        return $this->hasMany(Dokument::class);
    }

    public function wissen(): HasMany
    {
        return $this->hasMany(Wissen::class)->orderBy('sortierung');
    }

    public function erkenntnisse(): HasMany
    {
        return $this->hasMany(Erkenntnis::class);
    }

    public function uebergaben(): HasMany
    {
        return $this->hasMany(Uebergabe::class);
    }

    /**
     * Wissens-Ampel: wie gut ist das Ressort fuer eine Uebergabe vorbereitet?
     * Liefert Punkte (0 bis 100) und eine Liste offener Hinweise.
     */
    public function wissensstand(): array
    {
        $hinweise = [];
        $punkte = 0;

        if (filled($this->auftrag)) {
            $punkte += 15;
        } else {
            $hinweise[] = 'Auftrag des Ressorts beschreiben';
        }

        if ($this->leitung()) {
            $punkte += 15;
        } else {
            $hinweise[] = 'Ressortleitung bestimmen';
        }

        if ($this->mitglieder->count() >= 2) {
            $punkte += 15;
        } else {
            $hinweise[] = 'Zweite Person oder Nachfolge einbinden';
        }

        $aktiv = $this->vorlagen->where('aktiv', true);
        if ($aktiv->count() > 0) {
            $mitAnleitung = $aktiv->filter(fn ($v) => filled($v->anleitung))->count();
            $anteil = $mitAnleitung / $aktiv->count();
            $punkte += (int) round(30 * $anteil);
            if ($anteil < 1) {
                $hinweise[] = ($aktiv->count() - $mitAnleitung).' Vorlage(n) ohne Anleitung';
            }
        } else {
            $hinweise[] = 'Wiederkehrende Aufgaben als Vorlagen erfassen';
        }

        if ($this->kontakte->count() > 0) {
            $punkte += 10;
        } else {
            $hinweise[] = 'Wichtige Kontakte erfassen';
        }

        if ($this->wissen->count() > 0) {
            $punkte += 15;
        } else {
            $hinweise[] = 'Mindestens eine Wissensseite schreiben';
        }

        $stufe = $punkte >= 80 ? 'gut' : ($punkte >= 50 ? 'mittel' : 'tief');

        return ['punkte' => min(100, $punkte), 'stufe' => $stufe, 'hinweise' => $hinweise];
    }
}
