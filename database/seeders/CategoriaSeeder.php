<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Categoria;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['id' => 1, 'nombre' => 'Bebidas y Refrescos', 'descripcion' => 'Gaseosas, jugos naturales, agua embotellada y energizantes', 'estado' => 1],
            ['id' => 2, 'nombre' => 'Abarrotes y Almacén', 'descripcion' => 'Arroz, fideos, aceites, enlatados y granos secos', 'estado' => 1],
            ['id' => 3, 'nombre' => 'Lácteos y Huevos', 'descripcion' => 'Leche, quesos, yogures, mantequillas y huevos frescos', 'estado' => 1],
            ['id' => 4, 'nombre' => 'Panadería y Pastelería', 'descripcion' => 'Pan artesanal, galletas, kakes y empanadas', 'estado' => 1],
            ['id' => 5, 'nombre' => 'Snacks y Confitería', 'descripcion' => 'Papas fritas, chocolates, caramelos y frutos secos', 'estado' => 1],
            ['id' => 6, 'nombre' => 'Limpieza del Hogar', 'descripcion' => 'Detergentes, desinfectantes, lavavajillas y limpiadores', 'estado' => 1],
            ['id' => 7, 'nombre' => 'Cuidado Personal', 'descripcion' => 'Jabones, champú, crema dental y artículos de higiene', 'estado' => 1],
        ];

        foreach ($registros as $item) {
            Categoria::create($item);
        }
    }
}