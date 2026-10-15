<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use App\Models\User;

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

$request->session()->put(
    'login_bloqueo_hasta',
    now()->timestamp + $segundos
);
        }

        $credenciales = [
            'nombreUsuario' => $nombreUsuario,
            'password' => $datos['password'],
            'estado' => 1,
        ];

        if (! Auth::validate($credenciales)) {
            RateLimiter::hit($claveIntentos, $ventanaIntentos);

            $intentos = RateLimiter::attempts($claveIntentos);

            if ($intentos >= $maxIntentos) {
                // Los cinco minutos comienzan en el tercer fallo.
                RateLimiter::hit($claveBloqueo, $duracionBloqueo);
                $request->session()->put(
    'login_bloqueo_hasta',
    now()->timestamp + RateLimiter::availableIn($claveBloqueo)
);

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

        $usuario = DB::transaction(function () use ($credenciales) {
            $usuario = User::where('nombreUsuario', $credenciales['nombreUsuario'])->lockForUpdate()->firstOrFail();
            $ultimaActividad = $usuario->session_last_activity ?? $usuario->updated_at?->timestamp;
            if ($usuario->session_token && $ultimaActividad &&
                $ultimaActividad > now()->subMinutes((int) config('session.lifetime'))->timestamp) {
                return null;
            }
            $usuario->forceFill([
                'session_token' => bin2hex(random_bytes(32)),
                'session_last_activity' => now()->timestamp,
            ])->save();
            return $usuario;
        });
        if (!$usuario) {
            return back()->withErrors(['nombreUsuario' => 'Usuario ocupado. Acceso denegado: esta cuenta ya tiene una sesión activa.'])->withInput($request->only('nombreUsuario'));
        }
        Auth::login($usuario);
        // Inicio de sesión correcto.
        RateLimiter::clear($claveIntentos);
        RateLimiter::clear($claveBloqueo);

        $request->session()->forget('login_bloqueo_hasta');

        $request->session()->regenerate();
        $request->session()->put('cuenta_token', $usuario->session_token);

        return redirect()->route('home');
    }

    public function destroy(Request $request)
    {
        User::whereKey($request->user()->id)->where('session_token', $request->session()->get('cuenta_token'))
            ->update(['session_token' => null, 'session_last_activity' => null]);
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('success', 'Sesión cerrada correctamente.');
    }
}
