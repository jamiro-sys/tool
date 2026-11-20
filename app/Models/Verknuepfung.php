<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Verknuepfung extends Model
{
    protected $table = 'verknuepfungen';

    protected $fillable = ['titel', 'url', 'werkzeug'];

    public const WERKZEUGE = [
        'turnieragenda' => 'turnieragenda.ch',
        'helfereinsatz' => 'helfereinsatz.ch',
        'andere' => 'Anderes',
    ];

    /** Kurzname fuer den Bezug in Formularen. */
    public const BEZUEGE = ['ausgabe' => Ausgabe::class, 'ressort' => Ressort::class, 'aufgabe' => Aufgabe::class];

    public function bezug(): MorphTo
    {
        return $this->morphTo();
    }

    public function host(): string
    {
        return (string) parse_url($this->url, PHP_URL_HOST);
    }
}
