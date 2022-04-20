<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UsersFiltros extends Model
{
    protected $table = 'users_filtros';
    
    protected $fillable = [
        'UserCreated', 'Api', 'Consulta', 'created_at'
    ];
}
