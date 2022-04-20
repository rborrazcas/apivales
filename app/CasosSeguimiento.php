<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CasosSeguimiento extends Model
{
    protected $table = 'casos_seguimiento';
    //
    protected $fillable = [
    'id', 'FechaHora', 'idServicio', 'idEstadoActual', 'Comentario', 'Cama', 'created_at', 'updated_at', 'UserCreated', 'UserUpdated'
    ];
}
