<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushAbo extends Model
{
    protected $table = 'push_abos';

    protected $fillable = ['user_id', 'endpoint', 'public_key', 'auth_token', 'geraet'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
