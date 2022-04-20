<?php

namespace App\ModelsPulseras;

use Illuminate\Database\Eloquent\Model;

class CodigoBarra extends Model
{
    protected $table = 'bravos_codigobarras';
    //
    protected $fillable = [
        'id',
        'Folio',
        'CodigoBarras',
        'created_at',
        'updated_at',
        'UserCreated',
        'UserOwned',
        'UserUpdated'
    ];
}
