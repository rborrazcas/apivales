<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ValesValidaciones extends Model
{
    protected $table = 'vales_validaciones';
    public $timestamps = false;
    protected $fillable = [
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
        'idStatus',
        'Metodo',
        'Remesa',
        'isEntregado',
        'entrega_at',
        'UserEntrega',
        'cPlantilla',
        'cActoresPoliticos',
        'celectoralAbogados',
        'ccasasAzules',
        'cRCs',
        'cRGs',
        'cPromocion',
        'cPAN',
        'cMORENA',
        'cPRD',
        'cPRI',
        'cPVEM',
        'cVOTA',
        'cINE',
        'cMovimiento',
        'Duplicado',
        'ErrorCURP',
        'TrabajemosJuntos',
        'Calentadores',
        'ApoyoAlimentario',
        'ProyectosProductivos',
        'Despensas',
        'TarjetaDIF',
        'BecaEducafin',
        'FechaValidacion'        
    ];
}
