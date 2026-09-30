<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index()
    {
        $usuarios = User::with('rol')
            ->orderBy('nombreCompleto')
            ->get();

        return view('usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        $usuario = new User();
        $usuario->estado = 1;

        return $this->formulario($usuario);
    }

    public function store(Request $request)
    {
        $datos = $this->validarDatos($request);

        $datos['password'] = Hash::make($datos['password']);

        User::create($datos);

        return redirect()
            ->route('admin.indexusuario')
            ->with('success', 'Usuario registrado correctamente.');
    }

    public function show(User $usuario)
    {
        return redirect()->route('admin.editusuario', $usuario->id);
    }

    public function edit(User $usuario)
    {
        return $this->formulario($usuario);
    }

    public function update(Request $request, User $usuario)
    {
        $datos = $this->validarDatos($request, $usuario);

        // Evitar que el usuario conectado se quite su propio acceso.
        if ((int) $usuario->id === (int) $request->user()->id) {
            if ((int) $datos['estado'] !== 1) {
                throw ValidationException::withMessages([
                    'estado' => 'No puedes desactivar tu propia cuenta.',
                ]);
            }

            if ((int) $datos['rol_id'] !== (int) $usuario->rol_id) {
                throw ValidationException::withMessages([
                    'rol_id' => 'No puedes cambiar tu propio rol.',
                ]);
            }
        }

        if ($request->filled('password')) {
            $datos['password'] = Hash::make($datos['password']);
        } else {
            unset($datos['password']);
        }

        $usuario->update($datos);

        return redirect()
            ->route('admin.indexusuario')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(Request $request, User $usuario)
    {
        if ((int) $usuario->id === (int) $request->user()->id) {
            return redirect()
                ->route('admin.indexusuario')
                ->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        // Conservamos la cuenta y sus operaciones anteriores.
        $usuario->update(['estado' => 0]);

        return redirect()
            ->route('admin.indexusuario')
            ->with('success', 'Usuario desactivado correctamente.');
    }

    private function formulario(User $usuario)
    {
        $roles = Rol::where('estado', 1)
            ->orderBy('nombre')
            ->get();

        return view('usuarios.form', compact('usuario', 'roles'));
    }

    private function validarDatos(
        Request $request,
        ?User $usuario = null
    ): array {
        $usuarioUnico = Rule::unique('usuarios', 'nombreUsuario');

        if ($usuario !== null) {
            $usuarioUnico->ignore($usuario);
        }

        return $request->validate([
            'nombreCompleto' => ['required', 'string', 'max:255'],

            'nombreUsuario' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9._-]+$/',
                $usuarioUnico,
            ],

            'telefono' => ['nullable', 'string', 'max:30'],

            'rol_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'rol_id')->where('estado', 1),
            ],

            'estado' => ['required', 'boolean'],

            'password' => [
                $usuario === null ? 'required' : 'nullable',
                'string',
                'min:8',
                'max:64',
                'confirmed',
            ],
        ], [
            'nombreCompleto.required' => 'Escribe el nombre completo.',
            'nombreCompleto.max' => 'El nombre admite hasta 255 caracteres.',

            'nombreUsuario.required' => 'Escribe el nombre de usuario.',
            'nombreUsuario.max' => 'El usuario admite hasta 100 caracteres.',
            'nombreUsuario.regex' =>
                'Usa letras sin acentos, números, puntos, guiones o guiones bajos.',
            'nombreUsuario.unique' => 'Ese nombre de usuario ya está registrado.',

            'telefono.max' => 'El teléfono admite hasta 30 caracteres.',

            'rol_id.required' => 'Selecciona un rol.',
            'rol_id.integer' => 'El rol seleccionado no es válido.',
            'rol_id.exists' => 'Selecciona un rol existente y activo.',

            'estado.required' => 'Selecciona el estado de la cuenta.',
            'estado.boolean' => 'El estado seleccionado no es válido.',

            'password.required' => 'Escribe una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña admite hasta 64 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);
    }
}