<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Versand extends Model
{
    protected $table = 'versandprotokoll';

    protected $fillable = ['user_id', 'kanal', 'typ', 'status', 'fehler'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
