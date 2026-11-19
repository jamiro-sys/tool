<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VorlageVersion extends Model
{
    protected $table = 'vorlage_versionen';

    protected $fillable = ['vorlage_id', 'user_id', 'vorher', 'nachher', 'notiz'];

    protected function casts(): array
    {
        return ['vorher' => 'array', 'nachher' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
