<?php

namespace App\Http\Controllers;

use App\Models\Permiso;
use Illuminate\Http\Request;

class PermisoController extends Controller
{
    public function index()
    {
        // Listar todos los permisos del sistema
        $permisos = Permiso::all();
        return response()->json($permisos);
    }

    public function store(Request $request)
    {
        // Validar los datos de entrada para registrar un nuevo permiso
        $request->validate([
            'nombre' => 'required|string|unique:permisos,nombre',
            'descripcion' => 'nullable|string',
        ]);

        $permiso = Permiso::create($request->all());

        return response()->json([
            'message' => 'Permiso creado con éxito',
            'data' => $permiso
        ], 201);
    }

    public function show($id)
    {
        // Mostrar un permiso específico por su ID
        $permiso = Permiso::findOrFail($id);
        return response()->json($permiso);
    }

    public function update(Request $request, $id)
    {
        // Buscar el permiso existente o lanzar error 404
        $permiso = Permiso::findOrFail($id);

        // Validar los campos de manera opcional para actualizaciones
        $request->validate([
            'nombre' => 'sometimes|required|string|unique:permisos,nombre,' . $id,
            'descripcion' => 'nullable|string',
        ]);

        $permiso->update($request->all());

        return response()->json([
            'message' => 'Permiso actualizado con éxito',
            'data' => $permiso
        ]);
    }

    public function destroy($id)
    {
        // Eliminar el permiso del sistema
        $permiso = Permiso::findOrFail($id);
        $permiso->delete();

        return response()->json([
            'message' => 'Permiso eliminado con éxito'
        ]);
    }
}