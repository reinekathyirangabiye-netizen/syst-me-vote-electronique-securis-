<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Candidat extends Model
{
    protected $table = 'candidats';
    public $timestamps = false;
    protected $fillable = ['id_scrutin', 'nom_candidat', 'id_electeur'];
}
