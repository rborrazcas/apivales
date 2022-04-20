<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ValesStatus extends Model
{
    protected $table = 'vales_status';
    
    protected $fillable = [
        'id', 'Estatus', 'Clave', 'created_at', 'updated_at'
    ];
}
