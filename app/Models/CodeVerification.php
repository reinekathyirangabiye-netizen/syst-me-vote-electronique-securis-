<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodeVerification extends Model
{
    protected $table = 'code_verification';

    public $timestamps = false;

    protected $fillable = ['id_electeur', 'code_hache', 'date_expiration', 'utilise'];
}