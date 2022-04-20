<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CatPaciente extends Model
{
    //
    protected $table = 'cat_paciente';
    //
    protected $fillable = [
        'id', 'TipoPaciente', 'created_at', 'updated_at'];
}
