<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ValesGrupos extends Model
{
    protected $table = 'vales_grupos';
    //
    protected $fillable = [
        'id',
        'UserOwned',
        'ResponsableEntrega',
        'Enlace',
        'idMunicipio',
        'TotalAprobados',
        'Remesa',
        'created_at',
        'UserCreated',
        'updated_at',
        'Ejercicio',
    ];
}
