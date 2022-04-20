<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ETGrupo extends Model
{
    protected $table = 'et_grupo';
    //
    protected $fillable = [
        'id', 'idMunicipio', 'NombreGrupo', 'idStatus', 'created_at', 'UserCreated', 'UserUpdated'
    ];
}
