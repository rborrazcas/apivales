<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VVales extends Model
{
    protected $table = 'vales';
    
    protected $fillable = [
        'id', 
        'idIncidencia',
        'FechaSolicitud', 
        'CURP', 
        'Nombre', 
        'Paterno', 
        'Materno', 
        'Sexo', 
        'FechaNacimiento', 
        'Calle', 
        'NumExt', 
        'NumInt', 
        'Colonia', 
        'CP', 
        'idMunicipio', 
        'idLocalidad', 
        'TelFijo', 
        'TelCelular', 
        'Compania',
        'TelRecados',
        'CorreoElectronico', 
        'idStatus', 
        'Metodo',
        'Remesa',
        'isEntregado',
        'entrega_at',
        'created_at', 
        'updated_at', 
        'UserCreated', 
        'UserOwned',
        'UserUpdated',
        'isDocumentacionEntrega',
        'FechaDocumentacion',
        'idUserDocumentacion',
        'isEntregadoOwner',
        'idUserReportaEntrega',
        'FechaReportaEntrega',
        'ComentarioEntrega',
        'IngresoPercibido',
        'OtrosIngresos',
        'TotalIngresos',
        'NumeroPersonas',
        'Bloqueado',
        'BloqueadoDate',
        'BloqueadoUser',
        'Ocupacion',
        'OcupacionOtro'
    ];
}
