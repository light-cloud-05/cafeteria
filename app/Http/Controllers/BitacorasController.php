<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use Illuminate\Http\Request;

class BitacorasController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permiso:consultar_bitacora']);
    }

    public function index(Request $request)
    {
        $filtros = $request->validate([
            'usuario' => ['nullable', 'string', 'max:100'],
            'accion' => ['nullable', 'string', 'max:50'],
        ]);

        $consulta = Bitacora::with('usuario');

        if (!empty($filtros['usuario'])) {
            $nombreUsuario = $filtros['usuario'];

            $consulta->whereHas('usuario', function ($query) use (
                $nombreUsuario
            ) {
                $query->where('nombreUsuario', $nombreUsuario);
            });
        }

        if (!empty($filtros['accion'])) {
            $consulta->where('accion', $filtros['accion']);
        }

        $bitacoras = $consulta
            ->orderByDesc('fechaHora')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $acciones = Bitacora::query()
            ->select('accion')
            ->distinct()
            ->orderBy('accion')
            ->pluck('accion');

        return view(
            'bitacoras.index',
            compact('bitacoras', 'acciones')
        );
    }
}