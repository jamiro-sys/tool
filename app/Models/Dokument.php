<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dokument extends Model
{
    protected $table = 'dokumente';

    protected $fillable = ['ressort_id', 'aufgabe_id', 'vorlage_id', 'user_id', 'titel', 'typ', 'pfad', 'url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ressort(): BelongsTo
    {
        return $this->belongsTo(Ressort::class);
    }

    public function link(): string
    {
        return $this->typ === 'datei' ? route('dokumente.download', $this) : (string) $this->url;
    }
}
