<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Listar todos los usuarios con su respectivo rol relacionado
        $usuarios = User::with('rol')->get();
        return response()->json([
            'message' => 'Lista de usuarios obtenida con éxito',
            'data' => $usuarios
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Opcional para APIs (generalmente omitido, pero se mantiene por compatibilidad de estructura)
        return response()->json(['message' => 'Vista de creación no disponible en API'], 404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validar los datos de entrada para registrar un nuevo usuario
        $request->validate([
            'rol_id' => 'required|exists:roles,id',
            'nombreCompleto' => 'required|string|max:255',
            'nombreUsuario' => 'required|string|max:255|unique:users,nombreUsuario',
            'password' => 'required|string|min:6',
            'telefono' => 'nullable|string|max:50',
            'estado' => 'required|boolean',
        ]);

        $data = $request->all();
        $data['password'] = Hash::make($request->password);

        $usuario = User::create($data);

        return response()->json([
            'message' => 'Usuario registrado con éxito',
            'data' => $usuario
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $usuario)
    {
        // Mostrar un usuario específico cargando su rol
        $usuario->load('rol');
        return response()->json([
            'message' => 'Usuario encontrado con éxito',
            'data' => $usuario
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $usuario)
    {
        // Opcional para APIs
        return response()->json(['message' => 'Vista de edición no disponible en API'], 404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $usuario)
    {
        // Validar los campos de manera opcional (sometimes) para actualizaciones parciales o totales
        $request->validate([
            'rol_id' => 'sometimes|required|exists:roles,id',
            'nombreCompleto' => 'sometimes|required|string|max:255',
            'nombreUsuario' => 'sometimes|required|string|max:255|unique:users,nombreUsuario,' . $usuario->id,
            'password' => 'sometimes|required|string|min:6',
            'telefono' => 'nullable|string|max:50',
            'estado' => 'sometimes|required|boolean',
        ]);

        $data = $request->all();

        // Si se envía una nueva contraseña, hashearla antes de actualizar
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        } else {
            unset($data['password']);
        }

        $usuario->update($data);

        return response()->json([
            'message' => 'Usuario actualizado con éxito',
            'data' => $usuario
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $usuario)
    {
        // Eliminar el usuario del sistema
        $usuario->delete();

        return response()->json([
            'message' => 'Usuario eliminado con éxito'
        ]);
    }
}