<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Halle extends Model
{
    protected $table = 'hallen';

    protected $fillable = ['name', 'ort', 'adresse', 'hallenwart', 'telefon', 'notizen'];
}
