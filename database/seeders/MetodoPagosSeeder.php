<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MetodoPago;

class MetodoPagosSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['id' => 1, 'nombre' => 'Efectivo'],
            ['nombre' => 'QR'],
            ['nombre' => 'Mixto'],
        ];

        foreach ($registros as $item) {
            MetodoPago::firstOrCreate(['nombre' => $item['nombre']]);
        }
    }
}
