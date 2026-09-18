<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stock;

class StocksSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['id' => 1, 'producto_id' => 1, 'stock_actual' => 150.00, 'stock_minimo' => 20.00, 'fecha_actualizacion' => '2026-09-15 08:00:00'],
            ['id' => 2, 'producto_id' => 2, 'stock_actual' => 80.00, 'stock_minimo' => 15.00, 'fecha_actualizacion' => '2026-09-15 08:00:00'],
            ['id' => 3, 'producto_id' => 3, 'stock_actual' => 200.00, 'stock_minimo' => 30.00, 'fecha_actualizacion' => '2026-09-15 08:00:00'],
            ['id' => 4, 'producto_id' => 4, 'stock_actual' => 95.00, 'stock_minimo' => 10.00, 'fecha_actualizacion' => '2026-09-15 08:00:00'],
            ['id' => 5, 'producto_id' => 5, 'stock_actual' => 120.00, 'stock_minimo' => 25.00, 'fecha_actualizacion' => '2026-09-15 08:00:00'],
            ['id' => 6, 'producto_id' => 6, 'stock_actual' => 35.00, 'stock_minimo' => 5.00, 'fecha_actualizacion' => '2026-09-15 08:00:00'],
            ['id' => 7, 'producto_id' => 7, 'stock_actual' => 50.00, 'stock_minimo' => 10.00, 'fecha_actualizacion' => '2026-09-15 08:00:00'],
            ['id' => 8, 'producto_id' => 8, 'stock_actual' => 110.00, 'stock_minimo' => 15.00, 'fecha_actualizacion' => '2026-09-15 08:00:00'],
            ['id' => 9, 'producto_id' => 9, 'stock_actual' => 60.00, 'stock_minimo' => 8.00, 'fecha_actualizacion' => '2026-09-15 08:00:00'],
            ['id' => 10, 'producto_id' => 10, 'stock_actual' => 140.00, 'stock_minimo' => 20.00, 'fecha_actualizacion' => '2026-09-15 08:00:00'],
        ];

        foreach ($registros as $item) {
            Stock::create($item);
        }
    }
}