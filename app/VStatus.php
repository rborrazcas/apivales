<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VStatus extends Model
{
    protected $table = 'v_negocios_status';
    
    protected $fillable = [
        'id', 'Estatus', 'created_at', 'updated_at'
    ];
}
