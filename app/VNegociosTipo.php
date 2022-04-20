<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VNegociosTipo extends Model
{
    protected $table = 'v_negocios_tipo';
    
    protected $fillable = [
        'id', 'Tipo', 'created_at', 'updated_at', 'UserCreated', 'UserUpdated'
    ];
}
