<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TipoUsuario extends Model
{
    protected $table = 'cat_usertipo';
    //
    protected $fillable = [
        'id', 'TipoUser', 'Clave','created_at', 'updated_at',
    ];
}
