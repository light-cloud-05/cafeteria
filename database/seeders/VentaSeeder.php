<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Venta;

class VentaSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['id' => 1, 'pedido_id' => 1, 'usuario_id' => 3, 'fecha_hora' => '2026-09-14 09:16:00', 'total' => 31.00, 'metodo_pago' => 'Efectivo', 'monto_recibido' => 50.00, 'cambio' => 19.00, 'estado' => 'Completado'],
            ['id' => 2, 'pedido_id' => 2, 'usuario_id' => 3, 'fecha_hora' => '2026-09-14 11:31:00', 'total' => 41.50, 'metodo_pago' => 'Transferencia QR', 'monto_recibido' => 41.50, 'cambio' => 0.00, 'estado' => 'Completado'],
            ['id' => 3, 'pedido_id' => 3, 'usuario_id' => 4, 'fecha_hora' => '2026-09-14 14:21:00', 'total' => 18.00, 'metodo_pago' => 'Efectivo', 'monto_recibido' => 20.00, 'cambio' => 2.00, 'estado' => 'Completado'],
            ['id' => 4, 'pedido_id' => 4, 'usuario_id' => 4, 'fecha_hora' => '2026-09-14 17:46:00', 'total' => 60.00, 'metodo_pago' => 'Tarjeta de Débito', 'monto_recibido' => 60.00, 'cambio' => 0.00, 'estado' => 'Completado'],
            ['id' => 5, 'pedido_id' => 5, 'usuario_id' => 3, 'fecha_hora' => '2026-09-15 09:11:00', 'total' => 25.00, 'metodo_pago' => 'Efectivo', 'monto_recibido' => 30.00, 'cambio' => 5.00, 'estado' => 'Completado'],
            ['id' => 6, 'pedido_id' => 6, 'usuario_id' => 3, 'fecha_hora' => '2026-09-15 10:46:00', 'total' => 44.00, 'metodo_pago' => 'Transferencia QR', 'monto_recibido' => 44.00, 'cambio' => 0.00, 'estado' => 'Completado'],
            ['id' => 7, 'pedido_id' => 7, 'usuario_id' => 4, 'fecha_hora' => '2026-09-15 12:01:00', 'total' => 16.50, 'metodo_pago' => 'Efectivo', 'monto_recibido' => 20.00, 'cambio' => 3.50, 'estado' => 'Completado'],
        ];

        foreach ($registros as $item) {
            Venta::create($item);
        }
    }
}