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
            ['rol_id' => 2, 'nombre' => 'Gerente de Tienda', 'estado' => 1],
            ['rol_id' => 3, 'nombre' => 'Cajero', 'estado' => 1],
            ['rol_id' => 4, 'nombre' => 'Encargado de Almacén', 'estado' => 1],
            ['rol_id' => 5, 'nombre' => 'Auditor Interno', 'estado' => 1],
        ];

        foreach ($registros as $item) {
            Rol::create($item);
        }
    }
}