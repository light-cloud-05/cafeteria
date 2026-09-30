<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BitacorasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Obtener todas las bitácoras ordenadas de la más reciente a la más antigua
        $bitacoras = Bitacora::orderBy('fechaHora', 'desc')->get();
        
        return response()->json([
            'message' => 'Lista de bitácoras obtenida con éxito',
            'data' => $bitacoras
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
        // Generalmente las bitácoras se registran de forma automática desde el sistema/servidor,
        // pero se incluye el validador por si se requiere un registro manual.
        $request->validate([
            'idUsuario' => 'required|exists:usuarios,id',
            'tablaAfectada' => 'required|string|max:255',
            'idRegistroAfectado' => 'required|integer',
            'accion' => 'required|string|max:50',
            'valoresAnteriores' => 'nullable|string',
            'valoresNuevos' => 'nullable|string',
            'fechaHora' => 'required|date',
        ]);

        $bitacora = Bitacora::create($request->all());

        return response()->json([
            'message' => 'Registro de bitácora creado con éxito',
            'data' => $bitacora
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $bitacora = Bitacora::findOrFail($id);

        return response()->json([
            'message' => 'Bitácora encontrada con éxito',
            'data' => $bitacora
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
        // Nota: Las bitácoras por seguridad no suelen modificarse, pero se deja estructurado el método.
        $bitacora = Bitacora::findOrFail($id);

        $request->validate([
            'idUsuario' => 'sometimes|required|exists:usuarios,id',
            'tablaAfectada' => 'sometimes|required|string|max:255',
            'idRegistroAfectado' => 'sometimes|required|integer',
            'accion' => 'sometimes|required|string|max:50',
            'valoresAnteriores' => 'nullable|string',
            'valoresNuevos' => 'nullable|string',
            'fechaHora' => 'sometimes|required|date',
        ]);

        $bitacora->update($request->all());

        return response()->json([
            'message' => 'Bitácora actualizada con éxito',
            'data' => $bitacora
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $bitacora = Bitacora::findOrFail($id);
        $bitacora->delete();

        return response()->json([
            'message' => 'Registro de bitácora eliminado con éxito'
        ]);
    }
}