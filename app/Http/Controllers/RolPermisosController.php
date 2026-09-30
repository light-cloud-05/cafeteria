<?php

namespace App\Http\Controllers;

use App\Models\RolPermiso;
use Illuminate\Http\Request;

class RolPermisosController extends Controller
{
    public function index()
    {
        // Listar todas las asignaciones de permisos a roles con sus relaciones
        $rolPermisos = RolPermiso::with(['rol', 'permiso'])->get();
        return response()->json($rolPermisos);
    }

    public function store(Request $request)
    {
        // Validar los datos de entrada para asignar un permiso a un rol
        $request->validate([
            'rol_id' => 'required|exists:roles,id',
            'permiso_id' => 'required|exists:permisos,id',
        ]);

        $rolPermiso = RolPermiso::create($request->all());

        return response()->json([
            'message' => 'Permiso asignado al rol con éxito',
            'data' => $rolPermiso
        ], 201);
    }

    public function show($id)
    {
        // Mostrar una asignación específica por su ID con sus relaciones
        $rolPermiso = RolPermiso::with(['rol', 'permiso'])->findOrFail($id);
        return response()->json($rolPermiso);
    }

    public function update(Request $request, $id)
    {
        // Buscar la asignación existente o lanzar error 404
        $rolPermiso = RolPermiso::findOrFail($id);

        // Validar los campos de manera opcional (sometimes) para actualizaciones
        $request->validate([
            'rol_id' => 'sometimes|required|exists:roles,id',
            'permiso_id' => 'sometimes|required|exists:permisos,id',
        ]);

        $rolPermiso->update($request->all());

        return response()->json([
            'message' => 'Asignación de rol y permiso actualizada con éxito',
            'data' => $rolPermiso
        ]);
    }

    public function destroy($id)
    {
        // Eliminar la asignación de la base de datos
        $rolPermiso = RolPermiso::findOrFail($id);
        $rolPermiso->delete();

        return response()->json([
            'message' => 'Permiso removido del rol con éxito'
        ]);
    }
}