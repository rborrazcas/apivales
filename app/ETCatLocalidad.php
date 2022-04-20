<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ETCatLocalidad extends Model
{
    protected $table = 'et_cat_localidad';
    //
    protected $fillable = [
    'Id', 'IdMunicipio', 'Numero', 'Nombre', 'Longitud', 'Latitud', 'Ambito'
    ];
}
