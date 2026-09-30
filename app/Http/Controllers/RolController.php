<?php

namespace App\Http\Controllers;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RolController extends Controller
{
    public function index()
    {
        $roles = Rol::withCount(['usuarios', 'permisos'])
            ->orderBy('rol_id')
            ->get();

        return view('roles.index', compact('roles'));
    }

    public function edit(Rol $rol)
    {
        $rol->load('permisos');

        $permisos = Permiso::orderBy('nombre')->get();

        return view('roles.form', compact('rol', 'permisos'));
    }

    public function update(Request $request, Rol $rol)
    {
        $datos = $request->validate([
            'estado' => ['required', 'boolean'],
            'permisos' => ['sometimes', 'array'],
            'permisos.*' => [
                'required',
                'integer',
                'distinct',
                'exists:permisos,id',
            ],
        ], [
            'estado.required' => 'Selecciona el estado del rol.',
            'estado.boolean' => 'El estado no es válido.',
            'permisos.array' => 'La selección de permisos no es válida.',
            'permisos.*.integer' => 'Hay un permiso no válido.',
            'permisos.*.distinct' => 'No puedes repetir un permiso.',
            'permisos.*.exists' => 'Uno de los permisos ya no existe.',
        ]);

        $seleccionados = array_map(
            'intval',
            $datos['permisos'] ?? []
        );

        DB::transaction(function () use (
            $request,
            $rol,
            $datos,
            $seleccionados
        ) {
            $rolActual = Rol::where('rol_id', $rol->rol_id)
                ->lockForUpdate()
                ->firstOrFail();

            $esMiRol = (int) $request->user()->rol_id
                === (int) $rolActual->rol_id;

            if ($esMiRol) {
                if ((int) $datos['estado'] !== 1) {
                    throw ValidationException::withMessages([
                        'estado' => 'No puedes desactivar tu propio rol.',
                    ]);
                }

                $nombresSeleccionados = Permiso::whereIn(
                    'id',
                    $seleccionados
                )->pluck('nombre')->all();

                foreach (['gestion_roles', 'gestion_usuarios'] as $obligatorio) {
                    if (! in_array($obligatorio, $nombresSeleccionados, true)) {
                        throw ValidationException::withMessages([
                            'permisos' =>
                                'Tu rol debe conservar los permisos de gestionar roles y gestionar usuarios.',
                        ]);
                    }
                }
            }

            if (
                (int) $datos['estado'] === 0
                && $rolActual->usuarios()->where('estado', 1)->exists()
            ) {
                throw ValidationException::withMessages([
                    'estado' =>
                        'Este rol tiene usuarios activos. Reasígnalos o desactiva sus cuentas antes de desactivar el rol.',
                ]);
            }

            $idsActuales = $rolActual->permisos()
                ->pluck('permisos.id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $relaciones = [];

            foreach ($seleccionados as $idPermiso) {
                // Conservamos la fecha de los permisos ya asignados.
                $relaciones[$idPermiso] = in_array(
                    $idPermiso,
                    $idsActuales,
                    true
                ) ? [] : ['fechaAsignacion' => now()];
            }

            $rolActual->update([
                'estado' => $datos['estado'],
            ]);

            $rolActual->permisos()->sync($relaciones);
        });

        return redirect()
            ->route('admin.indexrol')
            ->with('success', 'Rol y permisos actualizados correctamente.');
    }
}