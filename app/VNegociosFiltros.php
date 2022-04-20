<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VNegociosFiltros extends Model
{
    protected $table = 'v_negocios_filtros';
    
    protected $fillable = [
        'id', 'api', 'idUser', 'created_at', 'updated_at', 'parameters'
    ];
}
