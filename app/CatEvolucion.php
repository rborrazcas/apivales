<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CatEvolucion extends Model
{
    protected $table = 'cat_evolucion';
    //
    protected $fillable = [
        'id', 'Evolucion', 'created_at', 'updated_at'];
}
