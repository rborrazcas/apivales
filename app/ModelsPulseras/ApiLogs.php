<?php

namespace App\ModelsPulseras;

use Illuminate\Database\Eloquent\Model;

class ApiLogs extends Model
{
    protected $table = 'bravos_api_logs';
    //
    protected $fillable = [
        'id',
        'DeviceSync',
        'Folio',
        'CodigoBarras',
        'FechaHoraAcceso',
        'NewInvitado',
        'isSync',
        'created_at',
        'updated_at'
    ];
}
