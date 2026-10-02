<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class SessionController extends Controller
{
    public function index()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombreUsuario' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ], [
            'nombreUsuario.required' => 'Ingresa tu nombre de usuario.',
            'nombreUsuario.max' => 'El usuario admite hasta 100 caracteres.',
            'password.required' => 'Ingresa tu contraseña.',
        ]);

        $nombreUsuario = trim($datos['nombreUsuario']);

        // La misma cuenta comparte contador en todos los navegadores.
        $identificador = hash(
            'sha256',
            mb_strtolower($nombreUsuario)
        );

        $claveIntentos = 'login:intentos:' . $identificador;
        $claveBloqueo = 'login:bloqueo:' . $identificador;

        $maxIntentos = 3;
        $ventanaIntentos = 15 * 60;
        $duracionBloqueo = 5 * 60;

        // Durante el bloqueo no comprobamos la contraseña.
        if (RateLimiter::tooManyAttempts($claveBloqueo, 1)) {
            $segundos = RateLimiter::availableIn($claveBloqueo);
            $minutos = (int) ceil($segundos / 60);

            return back()
                ->withErrors([
                    'nombreUsuario' =>
                        "Acceso bloqueado temporalmente. Intenta nuevamente en aproximadamente {$minutos} minuto(s), o recupera tu contraseña.",
                ])
                ->withInput($request->only('nombreUsuario'));
        }

        $credenciales = [
            'nombreUsuario' => $nombreUsuario,
            'password' => $datos['password'],
            'estado' => 1,
        ];

        if (! Auth::attempt($credenciales)) {
            RateLimiter::hit($claveIntentos, $ventanaIntentos);

            $intentos = RateLimiter::attempts($claveIntentos);

            if ($intentos >= $maxIntentos) {
                // Los cinco minutos comienzan en el tercer fallo.
                RateLimiter::hit($claveBloqueo, $duracionBloqueo);

                // Al terminar el bloqueo tendrá tres intentos nuevos.
                RateLimiter::clear($claveIntentos);

                return back()
                    ->withErrors([
                        'nombreUsuario' =>
                            'Alcanzaste 3 intentos fallidos. El acceso está bloqueado durante 5 minutos. También puedes recuperar tu contraseña.',
                    ])
                    ->withInput($request->only('nombreUsuario'));
            }

            $restantes = $maxIntentos - $intentos;

            return back()
                ->withErrors([
                    'nombreUsuario' =>
                        "Los datos son incorrectos o la cuenta está inactiva. Intentos restantes: {$restantes}.",
                ])
                ->withInput($request->only('nombreUsuario'));
        }

        // Inicio de sesión correcto.
        RateLimiter::clear($claveIntentos);
        RateLimiter::clear($claveBloqueo);

        $request->session()->regenerate();

        return redirect()->route('home');
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('success', 'Sesión cerrada correctamente.');
    }
}