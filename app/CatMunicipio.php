<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CatMunicipio extends Model
{
    protected $table = 'cat_municipio';
    //
    protected $fillable = [
        'id',
        'Municipio',
        'Clave',
        'idEstado',
        'created_at',
        'updated_at',
    ];
}
