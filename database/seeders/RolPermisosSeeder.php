<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Rol;
use App\Models\Permiso;

class RolPermisosSeeder extends Seeder
{
    public function run(array $rolesNuevos = []): void
    {
        $asignaciones = [
            'Administrador' => [
                'gestion_usuarios',
                'gestion_roles',
                'gestion_categorias',
                'gestion_productos',
                'gestion_proveedores',
                'registrar_entradas',
                'registrar_salidas',
                'registrar_ajustes',
                'consultar_stock',
                'consultar_stock_minimo',
                'buscar_productos',
                'ver_agotados',
                'consultar_historial_pedidos',
                'ver_reportes',
                'gestion_promociones',
                'consultar_bitacora',
            ],

            'Cajero' => [
                'registrar_salidas',
                'consultar_stock',
                'consultar_stock_minimo',
                'ver_agotados',
                'cobrar_ventas',
            ],

            'Mesero' => [
                'consultar_stock',
                'buscar_productos',
                'ver_agotados',
                'registrar_pedidos',
                'modificar_pedidos',
                'cambiar_estado_pedidos',
                'consultar_historial_pedidos',
            ],
        ];

        DB::transaction(function () use ($asignaciones, $rolesNuevos) {
            foreach ($asignaciones as $nombreRol => $nombresPermisos) {
                $rol = Rol::where('nombre', $nombreRol)->firstOrFail();

                // Solo inicializar roles nuevos; conservar las decisiones de la interfaz.
                if (! in_array($rol->getKey(), $rolesNuevos)) {
                    continue;
                }

                $relaciones = [];

                foreach ($nombresPermisos as $nombrePermiso) {
                    $permiso = Permiso::where('nombre', $nombrePermiso)
                        ->firstOrFail();

                    $relaciones[$permiso->id] = [
                        'fechaAsignacion' => now(),
                    ];
                }

                $rol->permisos()->sync($relaciones);
            }
        });
    }
}
