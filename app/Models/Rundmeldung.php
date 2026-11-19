<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rundmeldung extends Model
{
    protected $table = 'rundmeldungen';

    protected $fillable = ['user_id', 'ressort_id', 'titel', 'text', 'empfaenger'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ressort(): BelongsTo
    {
        return $this->belongsTo(Ressort::class);
    }
}
