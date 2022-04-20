<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ETAprobadosComite extends Model
{
    //
    protected $table = 'et_aprobadoscomite';
    //
    protected $fillable = [
    'id', 'fechahora', 'Nombre', 'Paterno', 'Materno', 'FechaNacimiento',
    'Sexo', 'EntidadNacimiento', 'CURP', 'Calle', 'Numero', 'Colonia',
    'CP', 'idMunicipio', 'idLocalidad', 'TipoGral', 'EstatusFolioIntermedio',
    'LastUpdate', 'UserCaptura', 'NombreC', 'PaternoC', 'MaternoC', 'FechaNacimientoC',
    'SexoC', 'EntidadNacimientoC', 'CalleC', 'NumeroC', 'NumeroInteriorC', 'ColoniaC',
    'idMunicipioC', 'idLocalidadC', 'CodigoPostalC', 'FolioC', 'INEValida', 'INEComentario',
    'CURPValida', 'CURPComentario', 'CompobateDomicilioValida', 'CompobateDomicilioComentario',
    'FormatoSolicitudValida', 'FormatoSolicitudComentario', 'CartaCompromisoValida', 
    'CartaCompromisoComentario', 'LastUpdateC', 'UserUpdateC', 'EstatusExpediente', 
    'EdadValida', 'Edad', 'NumeroPaquete', 'TipoComprobanteDomicilio', 
    'PaqueteDescripcion', 'et_aprobadoscomitecol'
    ];
}
