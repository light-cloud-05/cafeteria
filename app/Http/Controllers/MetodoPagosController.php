<?php

namespace App\Http\Controllers;

use App\Models\MetodoPago;
use Illuminate\Http\Request;

class MetodoPagosController extends Controller
{
    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(MetodoPago::all());
        }

        return view('ventas.metodos-pago');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|unique:metodo_pagos,nombre',
        ]);

        $metodo = MetodoPago::create($request->all());
        return response()->json([
            'message' => 'Método de pago creado con éxito',
            'data' => $metodo
        ], 201);
    }

    public function show($id)
    {
        $metodo = MetodoPago::findOrFail($id);
        return response()->json($metodo);
    }

    public function update(Request $request, $id)
    {
        $metodo = MetodoPago::findOrFail($id);

        $request->validate([
            'nombre' => 'sometimes|required|string|unique:metodo_pagos,nombre,' . $id . ',idPago',
        ]);

        $metodo->update($request->all());
        return response()->json([
            'message' => 'Método de pago actualizado con éxito',
            'data' => $metodo
        ]);
    }

    public function destroy($id)
    {
        $metodo = MetodoPago::findOrFail($id);
        $metodo->delete();
        return response()->json([
            'message' => 'Método de pago eliminado con éxito'
        ]);
    }
}
