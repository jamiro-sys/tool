<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wissen extends Model
{
    protected $table = 'wissen';

    protected $fillable = ['ressort_id', 'user_id', 'titel', 'inhalt', 'sortierung'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ressort(): BelongsTo
    {
        return $this->belongsTo(Ressort::class);
    }
}
