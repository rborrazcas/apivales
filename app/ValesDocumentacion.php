<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ValesDocumentacion extends Model
{
    protected $table = 'vales_documentacion';
    
    protected $fillable = [
        'id',
        'FolioSolicitud',
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
        'TelFijo',
        'TelCelular',
        'TelRecados',
        'Compania',
        'CorreoElectronico',
        'idLocalidad',
        'idMunicipio',
        'created_at',
        'updated_at',
        'UserCreated',
        'UserUpdated',
        'UserOwned',
        'idStatusDocumentacion',
        'TieneFolio',
        'TieneFechaSolicitud',
        'TieneCURPValida',
        'NombreCoincideConINE',
        'TieneDomicilio',
        'TieneArticuladorReverso',
        'FolioCoincideListado',
        'FechaSolicitudChange',
        'CURPCoincideListado',
        'NombreChanged',
        'PaternoChanged',
        'MaternoChanged',
        'CalleChanged',
        'NumExtChanged',
        'NumIntChanged',
        'ColoniaChanged',
        'idLocalidadChanged',
        'idMunicipioChanged',
        'UserOwnedchanged'
       ];
}
