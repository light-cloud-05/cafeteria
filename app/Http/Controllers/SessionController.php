<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    // Mostrar el formulario de inicio de sesión.
    public function index()
    {
        return view('auth.login');
    }

    // Validar las credenciales e iniciar sesión.
    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombreUsuario' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ], [
            'nombreUsuario.required' => 'Ingresa tu nombre de usuario.',
            'nombreUsuario.max' => 'El usuario no puede superar los 100 caracteres.',
            'password.required' => 'Ingresa tu contraseña.',
        ]);

        $credenciales = [
            'nombreUsuario' => $datos['nombreUsuario'],
            'password' => $datos['password'],
            'estado' => 1,
        ];

        if (! Auth::attempt($credenciales)) {
            return back()
                ->withErrors([
                    'nombreUsuario' =>
                        'Los datos son incorrectos o la cuenta está inactiva.',
                ])
                ->withInput($request->only('nombreUsuario'));
        }

        $request->session()->regenerate();

        return redirect()->route('home');
    }

    // Cerrar la sesión actual.
    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Sesión cerrada correctamente.');
    }
}