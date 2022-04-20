<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CatEstado extends Model
{
    protected $table = 'cat_estados';
    //
    protected $fillable = [
    'id',
    'Estado',
    'Clave',
    'created_at',
    'updated_at'];
}
