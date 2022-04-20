<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ETTarjetasAsignadas extends Model
{
    protected $table = 'et_tarjetas_asignadas';
    //
    protected $fillable = [
        'Terminacion', 
        'id', 
        'idGrupo', 
        'CURP', 
        'Nombre', 
        'Paterno', 
        'Materno', 
        'idMunicipio', 
        'idLocalidad', 
        'Calle', 
        'NumExt', 
        'NumInt', 
        'Colonia', 
        'CP', 
        'TipoGral', 
        'UserCreated', 
        'created_at', 
        'updated_at'
    ];
}
