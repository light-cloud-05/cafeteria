<?php

namespace App\Listeners;

use App\Models\Bitacora;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use App\Models\User;


class RegistrarEventoDeAcceso
{
    public function handle(
        Login|Logout|PasswordReset $event
    ): void {
$usuario = $event->user;

if (!($usuario instanceof User)) {
    return;
}

if (!$usuario->exists) {
    return;
}

        $accion = match (true) {
            $event instanceof Login => 'INICIO_SESION',
            $event instanceof Logout => 'CIERRE_SESION',
            $event instanceof PasswordReset => 'RECUPERACION_CONTRASENA',
        };

        Bitacora::create([
            'tablaAfectada' => 'usuarios',
            'idRegistroAfectado' => $usuario->getKey(),
            'accion' => $accion,
            'valoresAnteriores' => null,
            'valoresNuevos' => null,
            'fechaHora' => now(),
            'idUsuario' => $usuario->getKey(),
        ]);
    }
}