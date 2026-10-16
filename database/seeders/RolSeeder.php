<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Rol;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['rol_id' => 1, 'nombre' => 'Administrador', 'estado' => 1],
            ['rol_id' => 2, 'nombre' => 'Cajero', 'estado' => 1],
            ['rol_id' => 3, 'nombre' => 'Mesero', 'estado' => 1],
        ];

        foreach ($registros as $item) {
            Rol::firstOrCreate(['nombre' => $item['nombre']], ['estado' => $item['estado']]);
        }
    }
}
