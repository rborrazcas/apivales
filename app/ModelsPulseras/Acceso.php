<?php

namespace App\ModelsPulseras;

use Illuminate\Database\Eloquent\Model;

class Acceso extends Model
{
    protected $table = 'bravos_accesos';
    //
    protected $fillable = [
        'id',
        'Folio',
        'Nombres',
        'FechaHoraEscaneada',
        'Observacion',
        'created_at',
        'updated_at',
        'UserCreated',
        'UserOwned',
        'UserUpdated'
    ];
}
