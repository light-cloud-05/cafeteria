<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Pedido;

class PedidoSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['id' => 1, 'usuario_id' => 3, 'fechaHora' => '2026-09-14 09:15:00', 'total' => 31.00, 'estado' => 'Completado'],
            ['id' => 2, 'usuario_id' => 3, 'fechaHora' => '2026-09-14 11:30:00', 'total' => 41.50, 'estado' => 'Completado'],
            ['id' => 3, 'usuario_id' => 4, 'fechaHora' => '2026-09-14 14:20:00', 'total' => 18.00, 'estado' => 'Completado'],
            ['id' => 4, 'usuario_id' => 4, 'fechaHora' => '2026-09-14 17:45:00', 'total' => 60.00, 'estado' => 'Completado'],
            ['id' => 5, 'usuario_id' => 3, 'fechaHora' => '2026-09-15 09:10:00', 'total' => 25.00, 'estado' => 'Completado'],
            ['id' => 6, 'usuario_id' => 3, 'fechaHora' => '2026-09-15 10:45:00', 'total' => 44.00, 'estado' => 'Completado'],
            ['id' => 7, 'usuario_id' => 4, 'fechaHora' => '2026-09-15 12:00:00', 'total' => 16.50, 'estado' => 'Completado'],
            ['id' => 8, 'usuario_id' => 3, 'fechaHora' => '2026-09-15 14:30:00', 'total' => 33.00, 'estado' => 'Pendiente'],
        ];

        foreach ($registros as $item) {
            Pedido::create($item);
        }
    }
}