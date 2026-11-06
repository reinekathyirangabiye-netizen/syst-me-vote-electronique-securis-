<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Responsable extends Model
{
    protected $table = 'responsables';
    public $timestamps = false;
    protected $fillable = ['nom_utilisateur', 'mot_de_passe_hache'];
}
