<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use Illuminate\Http\Request;

class PedidoController extends Controller
{
    public function index()
    {
        // Listar todos los pedidos incluyendo las relaciones con usuario y detalles
        $pedidos = Pedido::with(['usuario', 'detalles'])->get();
        return response()->json($pedidos);
    }

    public function store(Request $request)
    {
        // Validar los datos de entrada para registrar un nuevo pedido
        $request->validate([
            'fecha_hora' => 'required|date',
            'total' => 'required|numeric|min:0',
            'estado' => 'required|string',
            'usuario_id' => 'required|exists:usuarios,id', // Asegúrate de que apunte a tu tabla de usuarios
        ]);

        $pedido = Pedido::create($request->all());

        return response()->json([
            'message' => 'Pedido registrado con éxito',
            'data' => $pedido
        ], 201);
    }

    public function show($id)
    {
        // Mostrar un pedido específico por su ID con sus relaciones
        $pedido = Pedido::with(['usuario', 'detalles'])->findOrFail($id);
        return response()->json($pedido);
    }

    public function update(Request $request, $id)
    {
        // Buscar el pedido existente o lanzar error 404
        $pedido = Pedido::findOrFail($id);

        // Validar los campos de manera opcional (sometimes) para actualizaciones parciales o totales
        $request->validate([
            'fecha_hora' => 'sometimes|required|date',
            'total' => 'sometimes|required|numeric|min:0',
            'estado' => 'sometimes|required|string',
            'usuario_id' => 'sometimes|required|exists:usuarios,id',
        ]);

        $pedido->update($request->all());

        return response()->json([
            'message' => 'Pedido actualizado con éxito',
            'data' => $pedido
        ]);
    }

    public function destroy($id)
    {
        // Eliminar o cancelar el pedido del sistema
        $pedido = Pedido::findOrFail($id);
        $pedido->delete();

        return response()->json([
            'message' => 'Pedido eliminado con éxito'
        ]);
    }
}