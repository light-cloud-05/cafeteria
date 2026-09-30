<?php

namespace App\Http\Controllers;

use App\Models\DetallePedido;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DetallePedidoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $detalles = DetallePedido::with(['pedido', 'producto'])->get();
        return response()->json([
            'message' => 'Lista de detalles de pedido obtenida con éxito',
            'data' => $detalles
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['message' => 'Vista no disponible en API'], 404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'pedido_id' => 'required|exists:pedidos,id',
            'producto_id' => 'required|exists:productos,id',
            'cantidad' => 'required|integer|min:1',
            'precio_unitario' => 'required|numeric',
            'subtotal' => 'required|numeric',
            'opcion' => 'nullable|string|max:255',
        ]);

        $detalle = DetallePedido::create($request->all());

        return response()->json([
            'message' => 'Detalle de pedido creado con éxito',
            'data' => $detalle
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $detalle = DetallePedido::with(['pedido', 'producto'])->findOrFail($id);
        return response()->json([
            'message' => 'Detalle de pedido encontrado con éxito',
            'data' => $detalle
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return response()->json(['message' => 'Vista no disponible en API'], 404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $detalle = DetallePedido::findOrFail($id);

        $request->validate([
            'pedido_id' => 'sometimes|required|exists:pedidos,id',
            'producto_id' => 'sometimes|required|exists:productos,id',
            'cantidad' => 'sometimes|required|integer|min:1',
            'precio_unitario' => 'sometimes|required|numeric',
            'subtotal' => 'sometimes|required|numeric',
            'opcion' => 'nullable|string|max:255',
        ]);

        $detalle->update($request->all());

        return response()->json([
            'message' => 'Detalle de pedido actualizado con éxito',
            'data' => $detalle
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $detalle = DetallePedido::findOrFail($id);
        $detalle->delete();

        return response()->json([
            'message' => 'Detalle de pedido eliminado con éxito'
        ]);
    }
}