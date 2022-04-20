<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VGiros extends Model
{
    protected $table = 'v_giros';
    
    protected $fillable = [
        'id', 'Giro', 'created_at', 'updated_at', 'UserCreated', 'UserUpdated'
    ];

    public function negocio(){ 
        return $this->belongsToMany('App\VNegocios');
    }
}
