<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    // Mostrar la solicitud de recuperación.
    public function index(Request $request)
    {
        return view('auth.recuperar', ['nombreUsuario' => mb_substr((string) $request->query('nombreUsuario', ''), 0, 100)]);
    }

    // Generar el enlace para una cuenta activa.
    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombreUsuario' => ['required', 'string', 'max:100'],
        ], [
            'nombreUsuario.required' => 'Escribe el usuario que necesita recuperar su contraseña.',
            'nombreUsuario.max' => 'El usuario admite hasta 100 caracteres.',
        ]);

        $usuario = User::where('nombreUsuario', $datos['nombreUsuario'])
            ->where('estado', 1)
            ->whereHas('rol', function ($consulta) {
                $consulta->where('estado', 1);
            })
            ->first();

        if (!in_array(config('mail.default'), ['smtp', 'sendmail', 'ses', 'ses-v2', 'mailgun', 'postmark', 'roundrobin'])) {
            return back()->withErrors(['nombreUsuario' => 'El envío de correo no está habilitado. Contacta al administrador.'])->withInput($request->only('nombreUsuario'));
        }

        if ($usuario !== null && filter_var($usuario->email, FILTER_VALIDATE_EMAIL)) {
            try {
                $estado = Password::sendResetLink([
                'email' => $usuario->email,
                'estado' => 1,
                ]);
                if (!in_array($estado, [Password::RESET_LINK_SENT, Password::RESET_THROTTLED])) {
                    throw new \RuntimeException('El broker de recuperación no pudo generar el enlace.');
                }
            } catch (\Throwable $e) {
                Password::deleteToken($usuario);
                // No registrar credenciales SMTP ni enlaces de recuperación.
                \Illuminate\Support\Facades\Log::error('Falló el envío de recuperación al administrador.', ['tipo' => get_class($e)]);
                return back()->withErrors(['nombreUsuario' => 'No se pudo enviar la solicitud al administrador. Intenta más tarde o contáctalo directamente.'])->withInput($request->only('nombreUsuario'));
            }
        }

        // Mismo mensaje para no revelar qué cuentas existen.
        return back()->with(
            'success',
            'Si el usuario corresponde a una cuenta activa con correo registrado, se ha enviado la solicitud al administrador. Él verificará tu identidad y te facilitará un enlace temporal. Si ya la solicitaste, espera un minuto antes de repetirla.'
        );
    }

    // Mostrar el formulario al abrir el enlace.
    public function edit(Request $request, string $token)
    {
        $datos = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        return view('auth.restablecer', [
            'token' => $token,
            'email' => $datos['email'],
        ]);
    }

    // Comprobar el token y guardar la nueva contraseña.
    public function update(Request $request)
    {
        $datos = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:64',
                'confirmed',
            ],
        ], [
            'token.required' => 'Falta el enlace de recuperación.',
            'email.required' => 'Falta el correo electrónico.',
            'email.email' => 'El correo no es válido.',
            'password.required' => 'Escribe una nueva contraseña.',
            'password.min' => 'Usa al menos 8 caracteres.',
            'password.max' => 'La contraseña admite hasta 64 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $usuarioRestablecido = null;
        $estado = Password::reset(
            [
                'email' => $datos['email'],
                'token' => $datos['token'],
                'password' => $datos['password'],
                'password_confirmation' =>
                    $request->input('password_confirmation'),
                'estado' => 1,
            ],
            function (User $usuario, string $password) use (&$usuarioRestablecido) {
                if (! $usuario->rol || ! $usuario->rol->estado) {
                    throw ValidationException::withMessages([
                        'email' =>
                            'No se pudo restablecer el acceso. Contacta al administrador.',
                    ]);
                }

$usuario->forceFill([
    'password' => Hash::make($password), 'session_token'=>null,
])->save();

// Desbloquear solo después de validar el enlace
// y guardar correctamente la nueva contraseña.
$identificador = hash(
    'sha256',
    mb_strtolower(trim($usuario->nombreUsuario))
);

RateLimiter::clear('login:intentos:' . $identificador);
RateLimiter::clear('login:bloqueo:' . $identificador);

event(new PasswordReset($usuario));
                $usuarioRestablecido = $usuario;
            }
        );

        if ($estado === Password::PASSWORD_RESET) {
            $request->session()->forget('login_bloqueo_hasta');
            $aviso = 'Contraseña actualizada. Se envió un aviso al correo personal del usuario con un enlace para elegir su propia contraseña.';
            try {
                // El broker ya consumió el enlace anterior; este nuevo token va solo al titular.
                $tokenPersonal = Password::createToken($usuarioRestablecido);
                $enlace = route('password.reset', ['token' => $tokenPersonal, 'email' => $usuarioRestablecido->email]);
                $usuarioRestablecido->notify(new \App\Notifications\ContrasenaRestablecida($usuarioRestablecido->nombreUsuario, $enlace));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Falló el aviso de contraseña restablecida al usuario.', ['tipo' => get_class($e)]);
                $aviso = 'Contraseña actualizada, pero no se pudo enviar el aviso al usuario. Contacta al administrador para coordinar el acceso.';
            }
            return redirect()
                ->route('login')
                ->with(
                    'success',
                    $aviso
                );
        }

        return back()
            ->withErrors([
                'email' =>
                    'El enlace no es válido, expiró o la cuenta no está disponible. Solicita uno nuevo.',
            ])
            ->withInput($request->only('email'));
    }
}
