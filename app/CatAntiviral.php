<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CatAntiviral extends Model
{
    //cat_antiviral
    protected $table = 'cat_antiviral';
    //
    protected $fillable = [
    'id',
    'Antiviral',
    'created_at',
    'updated_at'];
}
