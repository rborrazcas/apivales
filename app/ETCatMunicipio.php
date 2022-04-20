<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ETCatMunicipio extends Model
{
    protected $table = 'et_cat_municipio';
    //
    protected $fillable = [
        'Id', 'Nombre', 'Region', 'SubRegion'
    ];
}
