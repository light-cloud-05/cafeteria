<?php

namespace App\Http\Controllers;

use App\Models\MovimientoInventario;
use Illuminate\Http\Request;

class MovimientoInventarioController extends Controller
{
    public function index()
    {
        // Listar todos los movimientos de inventario con sus relaciones
        $movimientos = MovimientoInventario::with(['usuario', 'producto', 'proveedor'])->get();
        return response()->json($movimientos);
    }

    public function store(Request $request)
    {
        // Validar los datos de entrada para registrar un movimiento
        $request->validate([
            'tipo' => 'required|string|in:Entrada,Salida,Ajuste',
            'cantidad' => 'required|numeric',
            'fecha_hora' => 'required|date',
            'motivo' => 'nullable|string',
            'usuario_id' => 'required|exists:usuarios,id',
            'producto_id' => 'required|exists:productos,id',
            'proveedor_id' => 'required|exists:proveedores,id',
        ]);

        $movimiento = MovimientoInventario::create($request->all());

        return response()->json([
            'message' => 'Movimiento de inventario registrado con éxito',
            'data' => $movimiento
        ], 201);
    }

    public function show($id)
    {
        // Mostrar un movimiento de inventario específico
        $movimiento = MovimientoInventario::with(['usuario', 'producto', 'proveedor'])->findOrFail($id);
        return response()->json($movimiento);
    }

    public function update(Request $request, $id)
    {
        // Actualizar un movimiento de inventario existente
        $movimiento = MovimientoInventario::findOrFail($id);

        $request->validate([
            'tipo' => 'sometimes|required|string|in:Entrada,Salida,Ajuste',
            'cantidad' => 'sometimes|required|numeric',
            'fecha_hora' => 'sometimes|required|date',
            'motivo' => 'nullable|string',
            'usuario_id' => 'sometimes|required|exists:usuarios,id',
            'producto_id' => 'sometimes|required|exists:productos,id',
            'proveedor_id' => 'sometimes|required|exists:proveedores,id',
        ]);

        $movimiento->update($request->all());

        return response()->json([
            'message' => 'Movimiento de inventario actualizado con éxito',
            'data' => $movimiento
        ]);
    }

    public function destroy($id)
    {
        // Eliminar un registro de movimiento de inventario
        $movimiento = MovimientoInventario::findOrFail($id);
        $movimiento->delete();

        return response()->json([
            'message' => 'Movimiento de inventario eliminado con éxito'
        ]);
    }
}