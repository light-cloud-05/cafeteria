<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index()
    {
        // Listar todos los proveedores registrados
        $proveedores = Proveedor::all();
        return response()->json($proveedores);
    }

    public function store(Request $request)
    {
        // Validar los datos de entrada para registrar un nuevo proveedor
        $request->validate([
            'nombre' => 'required|string|unique:proveedores,nombre',
            'telefono' => 'nullable|string',
            'estado' => 'required|boolean',
        ]);

        $proveedor = Proveedor::create($request->all());

        return response()->json([
            'message' => 'Proveedor registrado con éxito',
            'data' => $proveedor
        ], 201);
    }

    public function show($id)
    {
        // Mostrar un proveedor específico por su ID
        $proveedor = Proveedor::findOrFail($id);
        return response()->json($proveedor);
    }

    public function update(Request $request, $id)
    {
        // Buscar el proveedor existente o lanzar error 404
        $proveedor = Proveedor::findOrFail($id);

        // Validar los campos de manera opcional (sometimes) para actualizaciones
        $request->validate([
            'nombre' => 'sometimes|required|string|unique:proveedores,nombre,' . $id,
            'telefono' => 'nullable|string',
            'estado' => 'sometimes|required|boolean',
        ]);

        $proveedor->update($request->all());

        return response()->json([
            'message' => 'Proveedor actualizado con éxito',
            'data' => $proveedor
        ]);
    }

    public function destroy($id)
    {
        // Eliminar el proveedor del sistema
        $proveedor = Proveedor::findOrFail($id);
        $proveedor->delete();

        return response()->json([
            'message' => 'Proveedor eliminado con éxito'
        ]);
    }
}