<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dokument extends Model
{
    protected $table = 'dokumente';

    protected $fillable = ['ressort_id', 'aufgabe_id', 'vorlage_id', 'user_id', 'titel', 'typ', 'pfad', 'original_name', 'groesse', 'mime', 'url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ressort(): BelongsTo
    {
        return $this->belongsTo(Ressort::class);
    }

    public function endung(): string
    {
        return $this->typ === 'datei' ? strtoupper(pathinfo($this->original_name ?: $this->pfad, PATHINFO_EXTENSION) ?: 'Datei') : 'Link';
    }

    public function groesseText(): ?string
    {
        if (! $this->groesse) {
            return null;
        }
        $kb = $this->groesse / 1024;

        return $kb >= 1024 ? number_format($kb / 1024, 1, '.', "'").' MB' : max(1, (int) round($kb)).' KB';
    }

    public function istBild(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function link(): string
    {
        return $this->typ === 'datei' ? route('dokumente.download', $this) : (string) $this->url;
    }
}
