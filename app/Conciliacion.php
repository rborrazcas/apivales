<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Conciliacion extends Model
{
    protected $table = 'preconciliacion_vales';
    protected $fillable = [
        'folio_vale',
        'idUser',
        'cantidad',
        'codigo',
        'responsable_de_escaneo',
        'farmacia',
        'fecha_de_canje',
        'tipo_de_operacion',
        'fecha_de_captura',
        'mes_de_canje',
        'observacion',
    ];
    public $timestamps = false;
}
