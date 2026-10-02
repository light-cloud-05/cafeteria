<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MiContrasenaController extends Controller
{
    public function index(Request $request)
    {
        $this->verificarCuentaActiva($request);

        return view('perfil.contrasena');
    }

    public function update(Request $request)
    {
        $this->verificarCuentaActiva($request);

        $datos = $request->validate([
            'current_password' => [
                'bail',
                'required',
                'string',
                'current_password:web',
            ],
            'password' => [
                'bail',
                'required',
                'string',
                'min:8',
                'max:64',
                'different:current_password',
                'confirmed',
            ],
        ], [
            'current_password.required' =>
                'Escribe tu contraseña actual.',
            'current_password.current_password' =>
                'La contraseña actual es incorrecta.',
            'password.required' =>
                'Escribe tu nueva contraseña.',
            'password.min' =>
                'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.max' =>
                'La nueva contraseña admite hasta 64 caracteres.',
            'password.different' =>
                'La nueva contraseña debe ser diferente de la actual.',
            'password.confirmed' =>
                'La confirmación no coincide con la nueva contraseña.',
        ]);

        $usuario = $request->user();

        $usuario->password = Hash::make($datos['password']);
        $usuario->save();

        $request->session()->regenerate();

        return redirect()
            ->route('perfil.contrasena')
            ->with('success', 'Tu contraseña fue actualizada correctamente.');
    }

    private function verificarCuentaActiva(Request $request): void
    {
        $usuario = $request->user();

        abort_unless(
            $usuario
            && $usuario->estado
            && $usuario->rol
            && $usuario->rol->estado,
            403,
            'Tu cuenta o tu rol están inactivos.'
        );
    }
}