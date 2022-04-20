<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CatTipoMuestra extends Model
{
    protected $table = 'cat_tipomuestra';
    //
    protected $fillable = [
        'id', 'TipoMuestra', 'created_at', 'updated_at'];
}
