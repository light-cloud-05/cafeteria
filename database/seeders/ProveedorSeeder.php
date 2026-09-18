<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Proveedor;

class ProveedorSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['id' => 1, 'nombre' => 'Distribuidora Global S.A.', 'telefono' => '+591 71023456', 'estado' => 1],
            ['id' => 2, 'nombre' => 'Comercializadora del Valle Ltda.', 'telefono' => '+591 72198765', 'estado' => 1],
            ['id' => 3, 'nombre' => 'Lácteos Santa Fe S.R.L.', 'telefono' => '+591 33456789', 'estado' => 1],
            ['id' => 4, 'nombre' => 'Importadora Andina S.A.', 'telefono' => '+591 76011223', 'estado' => 1],
            ['id' => 5, 'nombre' => 'Snacks y Alimentos del Sur', 'telefono' => '+591 78544332', 'estado' => 1],
        ];

        foreach ($registros as $item) {
            Proveedor::create($item);
        }
    }
}