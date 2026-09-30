<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    public function index()
    {
        // Listar todas las ventas con sus respectivas relaciones (pedido y usuario)
        $ventas = Venta::with(['pedido', 'usuario'])->get();
        return response()->json([
            'message' => 'Lista de ventas obtenida con éxito',
            'data' => $ventas
        ]);
    }

    public function store(Request $request)
    {
        // Validar los datos de entrada para registrar una nueva venta
        $request->validate([
            'fecha_hora' => 'required|date',
            'total' => 'required|numeric|min:0',
            'metodo_pago' => 'required|string',
            'monto_recibido' => 'required|numeric|min:0',
            'cambio' => 'required|numeric|min:0',
            'estado' => 'required|string',
            'pedido_id' => 'required|exists:pedidos,id', // O idPedido según tu esquema
            'usuario_id' => 'required|exists:usuarios,id',
        ]);

        $venta = Venta::create($request->all());

        return response()->json([
            'message' => 'Venta registrada con éxito',
            'data' => $venta
        ], 201);
    }

    public function show($id)
    {
        // Mostrar una venta específica con sus relaciones
        $venta = Venta::with(['pedido', 'usuario'])->findOrFail($id);
        
        return response()->json([
            'message' => 'Venta encontrada con éxito',
            'data' => $venta
        ]);
    }

    public function update(Request $request, $id)
    {
        // Buscar la venta existente o lanzar error 404
        $venta = Venta::findOrFail($id);

        // Validar los campos de manera opcional (sometimes) para actualizaciones
        $request->validate([
            'fecha_hora' => 'sometimes|required|date',
            'total' => 'sometimes|required|numeric|min:0',
            'metodo_pago' => 'sometimes|required|string',
            'monto_recibido' => 'sometimes|required|numeric|min:0',
            'cambio' => 'sometimes|required|numeric|min:0',
            'estado' => 'sometimes|required|string',
            'pedido_id' => 'sometimes|required|exists:pedidos,id',
            'usuario_id' => 'sometimes|required|exists:usuarios,id',
        ]);

        $venta->update($request->all());

        return response()->json([
            'message' => 'Venta actualizada con éxito',
            'data' => $venta
        ]);
    }

    public function destroy($id)
    {
        // Eliminar la venta del sistema
        $venta = Venta::findOrFail($id);
        $venta->delete();

        return response()->json([
            'message' => 'Venta eliminada con éxito'
        ]);
    }
}