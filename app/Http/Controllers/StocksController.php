<?php

namespace App\Http\Controllers;

use App\Models\Stock; // Asegúrate de que el modelo se llame Stock (o Stocks según tu archivo)
use Illuminate\Http\Request;

class StocksController extends Controller
{
    public function index()
    {
        // Listar todos los registros de stock incluyendo la relación con el producto
        $stocks = Stock::with('producto')->get();
        return response()->json($stocks);
    }

    public function store(Request $request)
    {
        // Validar los datos de entrada para registrar el stock de un producto
        $request->validate([
            'producto_id' => 'required|exists:productos,id|unique:stocks,producto_id',
            'stock_actual' => 'required|numeric|min:0',
            'stock_minimo' => 'required|numeric|min:0',
            'fecha_actualizacion' => 'required|date',
        ]);

        $stock = Stock::create($request->all());

        return response()->json([
            'message' => 'Stock registrado con éxito',
            'data' => $stock
        ], 201);
    }

    public function show($id)
    {
        // Mostrar un registro de stock específico con su producto relacionado
        $stock = Stock::with('producto')->findOrFail($id);
        return response()->json($stock);
    }

    public function update(Request $request, $id)
    {
        // Buscar el stock existente o lanzar error 404
        $stock = Stock::findOrFail($id);

        // Validar los campos de manera opcional (sometimes) para actualizaciones
        $request->validate([
            'producto_id' => 'sometimes|required|exists:productos,id|unique:stocks,producto_id,' . $id,
            'stock_actual' => 'sometimes|required|numeric|min:0',
            'stock_minimo' => 'sometimes|required|numeric|min:0',
            'fecha_actualizacion' => 'sometimes|required|date',
        ]);

        $stock->update($request->all());

        return response()->json([
            'message' => 'Stock actualizado con éxito',
            'data' => $stock
        ]);
    }

    public function destroy($id)
    {
        // Eliminar el registro de stock del sistema
        $stock = Stock::findOrFail($id);
        $stock->delete();

        return response()->json([
            'message' => 'Stock eliminado con éxito'
        ]);
    }
}