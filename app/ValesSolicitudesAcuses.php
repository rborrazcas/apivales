<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ValesSolicitudesAcuses extends Model
{
    protected $table = 'vales_solicitudes_acuses';
    //
    protected $fillable = [
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
        'idMunicipio',
        'Municipio',
        'Remesa',
        'Comentario',
        'created_at',
        'UserCreated',
        'updated_at',
    ];
}
