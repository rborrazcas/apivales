<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CatLocalidades extends Model
{
    protected $table = 'cat_localidades';

    protected $fillable = [
    'mapa',
    'cve_ent',
    'Entidad',
    'nom_abr',
    'cve_mun',
    'Municipio',
    'cve_loc',
    'nom_loc',
    'Ambito',
    'latitud',
    'longitud',
    'lat_decimal',
    'lon_decimal',
    'altitud',
    'cve_carta',
    'PoblacionTotal',
    'PoblacionMasculina',
    'PoblacionFemenina',
    'TotalViviendasHabitadas',
    ];
}
