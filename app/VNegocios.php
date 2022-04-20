<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VNegocios extends Model
{
    protected $table = 'v_negocios';
    
    protected $fillable = [
        'id', 
        'ClaveUnica', 
        'Codigo',
        'RFC',
        'NombreEmpresa',
        'Nombre',
        'Paterno',
        'Materno',
        'TelNegocio',
        'TelCasa',
        'Celular',
        'idMunicipio','Calle',
        'NumExt',
        'NumInt',
        'Colonia',
        'CP',
        'Correo',
        'Latitude',
        'Longitude',
        'FechaInscripcion',
        'HorarioAtencion',
        'idTipoNegocio',
        'QuiereTransferencia',
        'Banco',
        'CLABE',
        'NumTarjeta',
        'idStatus',
        'created_at',
        'updated_at',
        'validated_at',
        'UserCreated',
        'UserUpdated',
        'UserValidate',
        'Refrendo2021',
        'UserRefrendo',
        'FechaRefrendo2021'

    ];
    //

    public function funcion_giros(){ 
        return $this->belongsToMany('App\VGiros','App\VNegociosGiros','idNegocio','idGiro');
    }


}
