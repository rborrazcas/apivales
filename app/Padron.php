<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Padron extends Model
{
    protected $table = 'concentrado_prepadron';
    protected $fillable = [
        'Orden',
        'OrdenMunicipio',
        'Identificador',
        'Region',
        'Nombre',
        'Paterno',
        'Materno',
        'FechaNacimiento',
        'Sexo',
        'EstadoNacimiento',
        'CURP',
        'Validador',
        'Municipio',
        'NumLocalidad',
        'Localidad',
        'Colonia',
        'Calle',
        'NumExt',
        'NumInt',
        'CP',
        'Telefono',
        'Celular',
        'TelRecados',
        'FechaIne',
        'FolioTarjetaContigoSi',
        'Apoyo',
        'Variante',
        'Enlace',
        'LargoCURP',
        'FrecuenciaCURP',
        'Periodo',
        'NombreMenor',
        'PaternoMenor',
        'MaternoMenor',
        'FechaNacimientoMenor',
        'SexoMenor',
        'EstadoNacimientoMenor',
        'CURPMenor',
        'ValidadorCURPMenor',
        'LargoCURPMenor',
        'FrecuenciaCURPMenor',
        'EdadTutor',
        'EdadMenor',
        'idUsuarioCreo',
        'FechaCreo',
        'Remesa',
        'Folio',
    ];
    public $timestamps = false;
}
