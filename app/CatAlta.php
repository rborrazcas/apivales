<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CatAlta extends Model
{
    protected $table = 'cat_alta';
    //
    protected $fillable = [
    'id',
    'TipoAlta',
    'created_at',
    'updated_at'];

}
