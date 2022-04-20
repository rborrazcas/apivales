<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cedula extends Model
{
    protected $solicitudes_table = 'cedulas_solicitudes';
    
    protected $solicitudes_fillable = [
        'id', 
        'FechaSolicitud', 
        'FolioTarjetaImpulso', 
        'Nombre', 
        'Paterno', 
        'Materno', 
        'FechaNacimiento', 
        'Edad', 
        'Sexo', 
        'idEntidadNacimiento', 
        'CURP', 
        'RFC', 
        'idEstadoCivil',
        'idParentescoJefe',
        'NumHijos',
        'NumHijas',
        'ComunidadIndigena',
        'Dialecto',
        'Afromexicano',
        'idSituacionActual',
        'TarjetaImpulso',
        'ContactoTarjetaImpulso',
        'Celular', 
        'Telefono', 
        'TelRecados',
        'Correo',
        'idParentescoTutor',
        'NombreTutor',
        'PaternoTutor',
        'MaternoTutor',
        'FechaNacimientoTutor',
        'EdadTutor',
        'CURPTutor',
        'TelefonoTutor',
        'CorreoTutor',
        'NecesidadSolicitante',
        'CostoNecesidad',
        'idEntidadVive',
        'MunicipioVive',
        'LocalidadVive',
        'CPVive',
        'ColoniaVive',
        'CalleVive',
        'NoExtVive',
        'NoIntVive',
        'Referencias',
        'ListoParaEnviar',
        'idUsuarioCreo',
        'FechaCreo',
        'idUsuarioActualizo',
        'FechaActualizo'
    ];
}
