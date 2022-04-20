<?php

namespace App\ModelsPulseras;

use Illuminate\Database\Eloquent\Model;

class Invitado extends Model
{
    protected $table = 'bravos_invitados';
    //
    protected $primaryKey = 'Folio';
    protected $fillable = [
        'Folio',
        'CodigoBarras',
        'Responsable',
        'NumeroInvitado',
        'idMunicipio',
        'Nombres',
        'Materno',
        'Paterno',
        'CURP',
        'Celular',
        'NumeroBurbuja',
        'created_at',
        'updated_at',
        'UserCreated',
        'UserOwned',
        'UserUpdated',
        'isSync',
        'FechaSync',
        'DeviceSync',
        'FechaHoraAcceso',
        'NewInvitado'
    ];
}
