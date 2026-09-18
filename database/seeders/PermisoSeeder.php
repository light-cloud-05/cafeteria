<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permiso;

class PermisoSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['id' => 1, 'nombre' => 'gestion_usuarios', 'descripcion' => 'Crear, editar y eliminar usuarios del sistema'],
            ['id' => 2, 'nombre' => 'gestion_inventario', 'descripcion' => 'Registrar entradas, salidas y modificar stock de productos'],
            ['id' => 3, 'nombre' => 'procesar_ventas', 'descripcion' => 'Registrar pedidos, emitir cobros y comprobantes'],
            ['id' => 4, 'nombre' => 'cierre_caja', 'descripcion' => 'Realizar aperturas, arqueos y cierres de caja diarios'],
            ['id' => 5, 'nombre' => 'ver_reportes', 'descripcion' => 'Acceso a paneles analíticos y reportes consolidados'],
        ];

        foreach ($registros as $item) {
            Permiso::create($item);
        }
    }
}