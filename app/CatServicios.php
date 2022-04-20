<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CatServicios extends Model
{
    protected $table = 'cat_servicios';
    //
    protected $fillable = [
        'id', 'Servicio','UserUpdated', 'created_at', 'updated_at', 'deleted_at'];
}
