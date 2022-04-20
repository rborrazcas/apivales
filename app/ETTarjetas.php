<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ETTarjetas extends Model
{
    protected $table = 'et_tarjetas';
    //
    protected $fillable = [
        'Consecutivo', 'Terminacion', 'FOLDER', 'REGISTRO', 'REFERENCIA ALFABÉTICA', 
        'MONTO', 'NUMERO DE TARJETA', 'PÓLIZA CONTABLE', 'Nombre del beneficiario', 
        'Municipio del Beneficiario', 'Localidad o colonia del beneficiario', 
        'CURP', 'RFC'

    ];
}
