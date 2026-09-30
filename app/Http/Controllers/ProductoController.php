<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function index()
    {
        // Listar todos los productos incluyendo sus relaciones (categoría y proveedor)
        $productos = Producto::with(['categoria', 'proveedor'])->get();
        return response()->json($productos);
    }

    public function store(Request $request)
    {
        // Validar los datos de entrada para registrar un nuevo producto
        $request->validate([
            'nombre' => 'required|string|unique:productos,nombre',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'disponible' => 'required|boolean',
            'categoria_id' => 'required|exists:categorias,id',
            'proveedor_id' => 'required|exists:proveedores,id',
        ]);

        $producto = Producto::create($request->all());

        return response()->json([
            'message' => 'Producto registrado con éxito',
            'data' => $producto
        ], 201);
    }

    public function show($id)
    {
        // Mostrar un producto específico con sus relaciones
        $producto = Producto::with(['categoria', 'proveedor'])->findOrFail($id);
        return response()->json($producto);
    }

    public function update(Request $request, $id)
    {
        // Buscar el producto existente o lanzar error 404
        $producto = Producto::findOrFail($id);

        // Validar los campos de manera opcional (sometimes) para actualizaciones
        $request->validate([
            'nombre' => 'sometimes|required|string|unique:productos,nombre,' . $id,
            'descripcion' => 'nullable|string',
            'precio' => 'sometimes|required|numeric|min:0',
            'disponible' => 'sometimes|required|boolean',
            'categoria_id' => 'sometimes|required|exists:categorias,id',
            'proveedor_id' => 'sometimes|required|exists:proveedores,id',
        ]);

        $producto->update($request->all());

        return response()->json([
            'message' => 'Producto actualizado con éxito',
            'data' => $producto
        ]);
    }

    public function destroy($id)
    {
        // Eliminar el producto del sistema
        $producto = Producto::findOrFail($id);
        $producto->delete();

        return response()->json([
            'message' => 'Producto eliminado con éxito'
        ]);
    }
}