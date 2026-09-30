<?php

namespace App\Http\Controllers;

use App\Models\CierreCaja; // Asegúrate de que coincida con el nombre de tu modelo (ej. CierreCaja o cierre_cajas)
use Illuminate\Http\Request;

class CierreCajasController extends Controller
{
    public function index()
    {
        // Listar todos los cierres de caja con su respectivo usuario
        $cierres = CierreCaja::with('usuario')->get();
        return response()->json($cierres);
    }

    public function store(Request $request)
    {
        // Validar los datos de entrada para el cierre de caja
        $request->validate([
            'idUsuario' => 'required|exists:users,id', // Ajusta la tabla si es 'usuarios'
            'fechaInicio' => 'required|date',
            'fechaFin' => 'required|date|after_or_equal:fechaInicio',
            'totalSistema' => 'required|numeric|min:0',
            'totalDeclarado' => 'required|numeric|min:0',
            'diferencia' => 'required|numeric',
            'observacion' => 'nullable|string',
        ]);

        $cierre = CierreCaja::create($request->all());

        return response()->json([
            'message' => 'Cierre de caja registrado con éxito',
            'data' => $cierre
        ], 201);
    }

    public function show($id)
    {
        // Mostrar un cierre de caja específico por su ID
        $cierre = CierreCaja::with('usuario')->findOrFail($id);
        return response()->json($cierre);
    }

    public function update(Request $request, $id)
    {
        // Actualizar un cierre de caja existente
        $cierre = CierreCaja::findOrFail($id);

        $request->validate([
            'idUsuario' => 'sometimes|required|exists:users,id',
            'fechaInicio' => 'sometimes|required|date',
            'fechaFin' => 'sometimes|required|date',
            'totalSistema' => 'sometimes|required|numeric|min:0',
            'totalDeclarado' => 'sometimes|required|numeric|min:0',
            'diferencia' => 'sometimes|required|numeric',
            'observacion' => 'nullable|string',
        ]);

        $cierre->update($request->all());

        return response()->json([
            'message' => 'Cierre de caja actualizado con éxito',
            'data' => $cierre
        ]);
    }

    public function destroy($id)
    {
        // Eliminar un registro de cierre de caja
        $cierre = CierreCaja::findOrFail($id);
        $cierre->delete();

        return response()->json([
            'message' => 'Cierre de caja eliminado con éxito'
        ]);
    }
}