<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VNegociosPagadores extends Model
{
    //v_negocios_pagadores
    protected $table = 'v_negocios_pagadores';
    
    protected $fillable = [
        'id', 
        'idNegocio', 
        'CURP', 
        'Nombre', 
        'Paterno', 
        'Materno',
        'created_at', 
        'updated_at', 
        'UserCreated', 
        'UserUpdated', 
        'idStatus'
    ];
}
