<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'usuarios'; // 👈 ESTA LÍNEA ES LA QUE FALTA O ESTÁ MAL ESCRITA

    protected $fillable = [
        'rol_id',
        'nombreCompleto',
        'nombreUsuario',
        'password',
        'telefono',
        'estado',
    ];

    protected $hidden = [
        'password'
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}