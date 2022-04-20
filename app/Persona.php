<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    //
    protected $table = 'personas';
    
    protected $fillable = [
        'id', 'CURP', 'Nombre', 'Paterno', 'Materno', 'Sexo',
         'FechaNacimiento', 'idEntidadNacimiento', 'TelCasa',
          'TelCelular', 'Calle', 'NumExt', 'NumInt', 'Colonia', 'CP',
           'idMunicipio', 'idLocalidad', 'created_at', 'updated_at',
            'UserCreated', 'UserUpdated'
    ];

}
