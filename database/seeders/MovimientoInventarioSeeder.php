<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MovimientoInventario;

class MovimientoInventarioSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['id' => 1, 'usuario_id' => 5, 'producto_id' => 1, 'proveedor_id' => 1, 'tipo' => 'Entrada', 'cantidad' => 100.00, 'fecha_hora' => '2026-09-01 09:00:00', 'motivo' => 'Ingreso por compra según Factura #4021'],
            ['id' => 2, 'usuario_id' => 5, 'producto_id' => 3, 'proveedor_id' => 2, 'tipo' => 'Entrada', 'cantidad' => 150.00, 'fecha_hora' => '2026-09-02 10:30:00', 'motivo' => 'Reabastecimiento de almacén principal'],
            ['id' => 3, 'usuario_id' => 5, 'producto_id' => 5, 'proveedor_id' => 3, 'tipo' => 'Entrada', 'cantidad' => 80.00, 'fecha_hora' => '2026-09-03 11:15:00', 'motivo' => 'Lote de lácteos del proveedor'],
            ['id' => 4, 'usuario_id' => 5, 'producto_id' => 4, 'proveedor_id' => 4, 'tipo' => 'Entrada', 'cantidad' => 50.00, 'fecha_hora' => '2026-09-05 14:20:00', 'motivo' => 'Carga periódica de aceites'],
            ['id' => 5, 'usuario_id' => 5, 'producto_id' => 2, 'proveedor_id' => 1, 'tipo' => 'Ajuste', 'cantidad' => -2.00, 'fecha_hora' => '2026-09-10 16:00:00', 'motivo' => 'Ajuste por empaque dañado durante transporte'],
        ];

        foreach ($registros as $item) {
            MovimientoInventario::create($item);
        }
    }
}