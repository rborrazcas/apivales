<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ValesSolicitudes extends Model
{
    protected $table = 'vales_solicitudes';
    //
    protected $fillable = [
        'id',
        'Ejercicio',
        'idSolicitud',
        'CURP',
        'Nombre',
        'Paterno',
        'Materno',
        'CodigoBarrasInicial',
        'CodigoBarrasFinal',
        'SerieInicial',
        'SerieFinal',
        'idArticulador',
        'Articulador',
        'ResponsableEntrega',
        'Enlace',
        'idMunicipio',
        'Municipio',
        'Remesa',
        'Comentario',
        'created_at',
        'UserCreated',
        'updated_at',
    ];
}
