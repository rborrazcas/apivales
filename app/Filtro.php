<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Filtro extends Model
{
    protected $table = 'filtro';
    
    protected $fillable = [
    'id', 'idPaciente', 'idMunicipioProdencia', 'MotivoConsulta', 'TieneFiebre',
     'TieneTos', 'TieneCefaleas', 'TuvoExposicion', 'OtroSintoma', 'TiempoEvolucionSintomas',
      'Indicaciones', 'idServicio', 'FechaHoraServicio','created_at','updated_at','UserCreated',
      'UserUpdated',
    ];
}
