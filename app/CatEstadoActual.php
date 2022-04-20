<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CatEstadoActual extends Model
{
    protected $table = 'cat_estadoactual';
    //
    protected $fillable = [
        'id', 'EstadoActual', 'Clave', 'created_at', 'updated_at'];
}
