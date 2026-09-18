<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Producto;

class ProductoSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['id' => 1, 'categoria_id' => 1, 'proveedor_id' => 1, 'nombre' => 'Coca Cola 2.5L Retornable', 'descripcion' => 'Gaseosa sabor original botella de vidrio', 'precio' => 12.50, 'disponible' => 1],
            ['id' => 2, 'categoria_id' => 1, 'proveedor_id' => 2, 'nombre' => 'Jugo del Valle Naranja 1L', 'descripcion' => 'Jugo nectar con pulpa de naranja', 'precio' => 8.00, 'disponible' => 1],
            ['id' => 3, 'categoria_id' => 2, 'proveedor_id' => 2, 'nombre' => 'Arroz Grano Oro 1kg', 'descripcion' => 'Arroz blanco de primera calidad', 'precio' => 7.50, 'disponible' => 1],
            ['id' => 4, 'categoria_id' => 2, 'proveedor_id' => 4, 'nombre' => 'Aceite Finko 1L', 'descripcion' => 'Aceite vegetal comestible refinado', 'precio' => 11.00, 'disponible' => 1],
            ['id' => 5, 'categoria_id' => 3, 'proveedor_id' => 3, 'nombre' => 'Leche Pil Entera 1L', 'descripcion' => 'Leche pasteurizada en bolsa UHT', 'precio' => 6.00, 'disponible' => 1],
            ['id' => 6, 'categoria_id' => 3, 'proveedor_id' => 3, 'nombre' => 'Queso Criollo San Javier 500g', 'descripcion' => 'Queso fresco artesanal de mesa', 'precio' => 22.00, 'disponible' => 1],
            ['id' => 7, 'categoria_id' => 4, 'proveedor_id' => 1, 'nombre' => 'Pan Marraqueta (Docena)', 'descripcion' => 'Pan fresco del día crujiente', 'precio' => 6.00, 'disponible' => 1],
            ['id' => 8, 'categoria_id' => 5, 'proveedor_id' => 5, 'nombre' => 'Papas Fritas Lays 150g', 'descripcion' => 'Papas fritas con sal de mar', 'precio' => 10.50, 'disponible' => 1],
            ['id' => 9, 'categoria_id' => 6, 'proveedor_id' => 4, 'nombre' => 'Detergente OMO 1kg', 'descripcion' => 'Detergente en polvo para ropa', 'precio' => 18.00, 'disponible' => 1],
            ['id' => 10, 'categoria_id' => 7, 'proveedor_id' => 1, 'nombre' => 'Jabón Lux Rosas 125g', 'descripcion' => 'Jabón de tocador perfumado', 'precio' => 4.50, 'disponible' => 1],
        ];

        foreach ($registros as $item) {
            Producto::create($item);
        }
    }
}