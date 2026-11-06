<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Electeur extends Model
{
    protected $table = 'electeurs';
    public $timestamps = false;
    protected $fillable = ['nom', 'prenom', 'matricule', 'email', 'peut_voter'];
}
