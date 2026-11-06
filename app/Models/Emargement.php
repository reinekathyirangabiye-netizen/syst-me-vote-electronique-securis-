<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Emargement extends Model
{
    protected $table = 'emargements';
    public $timestamps = false;
    protected $fillable = ['id_scrutin', 'id_electeur'];
}