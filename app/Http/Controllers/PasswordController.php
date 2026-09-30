<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    // Mostrar la solicitud de recuperación.
    public function index()
    {
        return view('auth.recuperar');
    }

    // Generar el enlace para una cuenta activa.
    public function store(Request $request)
    {
        $datos = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'Escribe tu correo electrónico.',
            'email.email' => 'Escribe un correo válido.',
            'email.max' => 'El correo admite hasta 255 caracteres.',
        ]);

        $usuario = User::where('email', $datos['email'])
            ->where('estado', 1)
            ->whereHas('rol', function ($consulta) {
                $consulta->where('estado', 1);
            })
            ->first();

        if ($usuario !== null) {
            Password::sendResetLink([
                'email' => $usuario->email,
                'estado' => 1,
            ]);
        }

        // Mismo mensaje para no revelar qué cuentas existen.
        return back()->with(
            'success',
            'Si el correo corresponde a una cuenta activa, se tramitará la recuperación. Si ya la solicitaste, espera un minuto antes de repetirla.'
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

        $estado = Password::reset(
            [
                'email' => $datos['email'],
                'token' => $datos['token'],
                'password' => $datos['password'],
                'password_confirmation' =>
                    $request->input('password_confirmation'),
                'estado' => 1,
            ],
            function (User $usuario, string $password) {
                if (! $usuario->rol || ! $usuario->rol->estado) {
                    throw ValidationException::withMessages([
                        'email' =>
                            'No se pudo restablecer el acceso. Contacta al administrador.',
                    ]);
                }

                $usuario->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                event(new PasswordReset($usuario));
            }
        );

        if ($estado === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with(
                    'success',
                    'Contraseña actualizada. Ya puedes iniciar sesión.'
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