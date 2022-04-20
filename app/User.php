<?php

namespace App;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;


class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'email', 
        'password',
        'Nombre', 
        'Paterno', 
        'Materno', 
        'idTipoUser', 
        'TelCasa', 
        'TelCelular', 
        'Correo', 
        'Calle', 
        'NumExt', 
        'NumInt', 
        'Colonia', 
        'CP', 
        'idMunicipio', 
        'idLocalidad', 
        'defaultPage', 
        'DevideID', 
        'DeviceOS', 
        'Token', 
        'remember_token', 
        'Foto64', 
        'created_at', 
        'updated_at', 
        'email_verified_at', 
        'idStatus', 
        'UserUpdated', 
        'UserCreated'

    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

}
