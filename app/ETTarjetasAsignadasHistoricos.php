<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ETTarjetasAsignadasHistoricos extends Model
{
    protected $table = 'et_tarjetas_asignadas_historico';
    //
    protected $fillable = [
        'uid', 'Terminacion', 'id', 'idGrupo', 'CURP', 'Nombre',
         'Paterno', 'Materno', 'idMunicipio', 'idLocalidad', 'Calle', 
         'NumExt', 'NumInt', 'Colonia', 'CP', 'TipoGral', 'UserCreated', 
         'created_at', 'UserDeleted', 'deleted_at', 'updated_at'

    ];
}
