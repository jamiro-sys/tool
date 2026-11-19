<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deployment extends Model
{
    protected $fillable = ['version', 'commit', 'aenderungen', 'status'];
}
