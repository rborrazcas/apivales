<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VUsersMenus extends Model
{
    protected $table = 'users_menus';
    
    protected $fillable = [
        'idUser',
        'idMenu', 
        'Ver', 
        'Agregar', 
        'Editar', 
        'Eliminar', 
        'Seguimiento', 
        'Exportar', 
        'Imprimir', 
        'ViewAll', 
        'created_at', 
        'updated_at', 
        'UserCreated', 
        'UserUpdated'
    ];
}
