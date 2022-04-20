<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ValesSeries extends Model
{
    //
    protected $table = 'vales_series';
    public  $timestamps=false;
    protected $fillable = ['Serie','CodigoBarra','created_at','updated_at'];
}
