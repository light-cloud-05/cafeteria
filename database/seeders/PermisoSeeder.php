<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permiso;

class PermisoSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'gestion_usuarios' => 'Administrar cuentas de usuarios',
            'gestion_roles' => 'Administrar roles y permisos',
            'gestion_categorias' => 'Administrar categorías',
            'gestion_productos' => 'Administrar productos',
            'gestion_proveedores' => 'Administrar proveedores',

            'registrar_entradas' => 'Registrar entradas de inventario',
            'registrar_salidas' => 'Registrar salidas de inventario',
            'registrar_ajustes' => 'Registrar ajustes de inventario',
            'consultar_stock' => 'Consultar existencias actuales',
            'consultar_stock_minimo' => 'Consultar productos con stock mínimo',
            'buscar_productos' => 'Buscar productos en el catálogo',
            'ver_agotados' => 'Consultar avisos de productos agotados',

            'registrar_pedidos' => 'Registrar pedidos',
            'modificar_pedidos' => 'Modificar o cancelar pedidos sin cobrar',
            'cambiar_estado_pedidos' => 'Actualizar el estado de los pedidos',
            'cobrar_ventas' => 'Registrar ventas y pagos',
            'consultar_historial_pedidos' => 'Consultar historial de pedidos',

            'ver_reportes' => 'Consultar reportes de ventas e inventario',
            'gestion_promociones' => 'Administrar promociones y descuentos',
            'consultar_bitacora' => 'Consultar la bitácora del sistema',
        ];

        foreach ($permisos as $nombre => $descripcion) {
            Permiso::updateOrCreate(
                ['nombre' => $nombre],
                ['descripcion' => $descripcion]
            );
        }
    }
}