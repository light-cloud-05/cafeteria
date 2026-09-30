<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use Illuminate\Http\Request;

class RolController extends Controller
{
    /// Lista los elementos de una tabla MOSTRAR
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = Rol::all();
        return response()->json([
            'message' => 'Lista de roles obtenida con éxito',
            'data' => $roles
        ]);
    }

    /// Guardar datos en la tabla CREAR
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|unique:roles,nombre',
            'estado' => 'required|boolean',
        ]);

        $rol = Rol::create($request->all());

        return response()->json([
            'message' => 'Rol guardado con éxito',
            'data' => $rol
        ], 201);
    }

    // Muestra solo un elemento de la tabla
    public function show(Rol $rol)
    {
        return response()->json([
            'message' => 'Rol encontrado con éxito',
            'data' => $rol
        ]);
    }

    // Modificar un elemento MODIFICAR
    public function update(Request $request, Rol $rol)
    {
        $request->validate([
            'nombre' => 'sometimes|required|string|unique:roles,nombre,' . $rol->id,
            'estado' => 'sometimes|required|boolean',
        ]);

        $rol->update($request->all());

        return response()->json([
            'message' => 'Rol modificado con éxito',
            'data' => $rol
        ]);
    }

    // Permite eliminar un elemento de la tabla ELIMINAR CRUD
    public function destroy(Rol $rol)
    {
        $rol->delete();

        return response()->json([
            'message' => 'Rol eliminado con éxito'
        ]);
    }
}