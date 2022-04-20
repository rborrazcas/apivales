<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CatCP extends Model
{
    protected $table = 'cat_cp';
    //
    protected $fillable = [
        'id', 'd_codigo', 'd_asenta', 'd_tipo_asenta', 'D_mnpio', 'd_estado',
        'd_ciudad', 'd_CP', 'c_estado', 'c_oficina', 'c_CP', 'c_tipo_asenta',
        'c_mnpio', 'id_asenta_cpcons', 'd_zona', 'c_cve_ciudad'];
}
