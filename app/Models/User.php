<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'usuarios'; 

    protected $fillable = [
      'rol_id',
      'nombreCompleto',
      'nombreUsuario',
      'email',
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
    public function rol()
    {
        return $this->belongsTo(
           Rol::class,
              'rol_id',
              'rol_id'
        );
    }

    public function tienePermiso(string $nombre): bool
    {
         if ((int) $this->estado !== 1) {
            return false;
        }

    $rol = $this->rol;

         if (! $rol || (int) $rol->estado !== 1) {
            return false;
        }

       return $rol->permisos()
          ->where('permisos.nombre', $nombre)
          ->exists();
    }
}