<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CatParentesco extends Model
{
    protected $table = 'cat_parentesco';
    //
    protected $fillable = [
        'id', 'Parentesco', 'created_at', 'updated_at'];
}
