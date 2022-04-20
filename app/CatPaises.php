<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CatPaises extends Model
{
    protected $table = 'cat_paises';
    //
    protected $fillable = [
        'id', 'Pais', 'Clave', 'created_at', 'updated_at'];
}
