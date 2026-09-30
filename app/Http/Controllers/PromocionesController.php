<?php

namespace App\Http\Controllers;

use App\Models\Promocion; // O App\Models\Promociones según tu definición de modelo
use Illuminate\Http\Request;

class PromocionesController extends Controller
{
    public function index()
    {
        // Listar todas las promociones registradas
        $promociones = Promocion::all();
        return response()->json($promociones);
    }

    public function store(Request $request)
    {
        // Validar los datos de entrada para registrar una nueva promoción
        $request->validate([
            'nombre' => 'required|string|unique:promociones,nombre',
            'tipo' => 'required|string',
            'aplicaSobre' => 'required|string',
            'valor' => 'required|numeric|min:0',
            'topeDescuento' => 'nullable|numeric|min:0',
            'fechaInicio' => 'required|date',
            'fechaFin' => 'required|date|after_or_equal:fechaInicio',
            'horaInicio' => 'nullable|string',
            'horaFin' => 'nullable|string',
            'activo' => 'required|boolean',
        ]);

        $promocion = Promocion::create($request->all());

        return response()->json([
            'message' => 'Promoción creada con éxito',
            'data' => $promocion
        ], 201);
    }

    public function show($id)
    {
        // Mostrar una promoción específica por su ID
        $promocion = Promocion::findOrFail($id);
        return response()->json($promocion);
    }

    public function update(Request $request, $id)
    {
        // Buscar la promoción existente o lanzar error 404
        $promocion = Promocion::findOrFail($id);

        // Validar los campos de manera opcional (sometimes) para actualizaciones
        $request->validate([
            'nombre' => 'sometimes|required|string|unique:promociones,nombre,' . $id,
            'tipo' => 'sometimes|required|string',
            'aplicaSobre' => 'sometimes|required|string',
            'valor' => 'sometimes|required|numeric|min:0',
            'topeDescuento' => 'nullable|numeric|min:0',
            'fechaInicio' => 'sometimes|required|date',
            'fechaFin' => 'sometimes|required|date|after_or_equal:fechaInicio',
            'horaInicio' => 'nullable|string',
            'horaFin' => 'nullable|string',
            'activo' => 'sometimes|required|boolean',
        ]);

        $promocion->update($request->all());

        return response()->json([
            'message' => 'Promoción actualizada con éxito',
            'data' => $promocion
        ]);
    }

    public function destroy($id)
    {
        // Eliminar la promoción del sistema
        $promocion = Promocion::findOrFail($id);
        $promocion->delete();

        return response()->json([
            'message' => 'Promoción eliminada con éxito'
        ]);
    }
}