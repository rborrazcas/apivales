<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VNegociosGiros extends Model
{
    protected $table = 'v_negocios_giros';
    
    protected $fillable = [
        'id','idNegocio', 'idGiro', 'created_at', 'UserCreated', 'updated_at', 'UserUpdated'
    ];

    public function giro(){ 
        return $this->belongsTo('App\VGiros','idGiro');
    }
    public function reference_is(){ 
        return $this->belongsTo('App\VNegocios','idNegocio');
    }
}
