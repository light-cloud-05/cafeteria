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
            ['id' => 2, 'nombre' => 'Tarjeta de Débito'],
            ['id' => 3, 'nombre' => 'Tarjeta de Crédito'],
            ['id' => 4, 'nombre' => 'Transferencia QR'],
            ['id' => 5, 'nombre' => 'Billetera Móvil'],
        ];

        foreach ($registros as $item) {
            MetodoPago::create($item);
        }
    }
}