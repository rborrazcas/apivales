<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ETGrupoUsers extends Model
{
    protected $table = 'et_grupo_users';
    //
    protected $fillable = [
        'idGrupo', 'idUser', 'created_at'
    ];
}
