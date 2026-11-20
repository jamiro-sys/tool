<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Unterordner in der Dateiablage, gehoert zu einem Ressort oder zu Allgemein. */
class Ordner extends Model
{
    protected $table = 'ordner';

    protected $fillable = ['ressort_id', 'name', 'sortierung'];

    public function ressort(): BelongsTo
    {
        return $this->belongsTo(Ressort::class);
    }

    public function dokumente(): HasMany
    {
        return $this->hasMany(Dokument::class);
    }

    public function pfad(): string
    {
        return ($this->ressort?->name ?? 'Allgemein').' / '.$this->name;
    }

    /**
     * Auswahl fuer "Verschieben nach": je Ressort (und Allgemein) direkt oder in einen Unterordner.
     *
     * @return array<string, array<string, string>>
     */
    public static function orte(iterable $ressorts, iterable $ordner): array
    {
        $ordner = collect($ordner);
        $orte = [];
        foreach ([null, ...collect($ressorts)->all()] as $r) {
            $name = $r?->name ?? 'Allgemein';
            $liste = ['r:'.($r?->id ?? 0) => $name.' (direkt)'];
            foreach ($ordner->where('ressort_id', $r?->id) as $o) {
                $liste['o:'.$o->id] = $name.' / '.$o->name;
            }
            $orte[$name] = $liste;
        }

        return $orte;
    }

    /** Gruppen fuer die Ordneransicht: Allgemein zuerst, dann die Ressorts. */
    public static function gruppen(iterable $ressorts, iterable $ordner, iterable $dokumente, bool $mitAllgemein = true): array
    {
        $ordner = collect($ordner);
        $dokumente = collect($dokumente);
        $liste = $mitAllgemein ? [null, ...collect($ressorts)->all()] : collect($ressorts)->all();

        return collect($liste)->map(fn ($r) => [
            'ressort' => $r,
            'ordner' => $ordner->where('ressort_id', $r?->id)->values(),
            'dokumente' => $dokumente->where('ressort_id', $r?->id)->values(),
        ])->all();
    }
}
