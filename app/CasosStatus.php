<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CasosStatus extends Model
{
    protected $table = 'casos_status';
    //
    protected $fillable = [
        'id', 'Estatus', 'Clave', 'created_at', 'updated_at'
    
    ];
}
