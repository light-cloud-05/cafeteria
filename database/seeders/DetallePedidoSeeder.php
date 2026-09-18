<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DetallePedido;

class DetallePedidoSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['id' => 1, 'pedido_id' => 1, 'producto_id' => 1, 'promocion_id' => 1, 'cantidad' => 2, 'precioUnitario' => 12.50, 'opcion' => 'Sin opción', 'subtotal' => 25.00],
            ['id' => 2, 'pedido_id' => 1, 'producto_id' => 5, 'promocion_id' => null, 'cantidad' => 1, 'precioUnitario' => 6.00, 'opcion' => 'Frío', 'subtotal' => 6.00],
            ['id' => 3, 'pedido_id' => 2, 'producto_id' => 3, 'promocion_id' => null, 'cantidad' => 2, 'precioUnitario' => 7.50, 'opcion' => 'Sin opción', 'subtotal' => 15.00],
            ['id' => 4, 'pedido_id' => 2, 'producto_id' => 4, 'promocion_id' => null, 'cantidad' => 1, 'precioUnitario' => 11.00, 'opcion' => 'Sin opción', 'subtotal' => 11.00],
            ['id' => 5, 'pedido_id' => 2, 'producto_id' => 8, 'promocion_id' => 2, 'cantidad' => 1, 'precioUnitario' => 10.50, 'opcion' => 'Sin opción', 'subtotal' => 10.50],
            ['id' => 6, 'pedido_id' => 2, 'producto_id' => 10, 'promocion_id' => null, 'cantidad' => 1, 'precioUnitario' => 5.00, 'opcion' => 'Sin opción', 'subtotal' => 5.00],
            ['id' => 7, 'pedido_id' => 3, 'producto_id' => 2, 'promocion_id' => null, 'cantidad' => 1, 'precioUnitario' => 8.00, 'opcion' => 'Frío', 'subtotal' => 8.00],
            ['id' => 8, 'pedido_id' => 3, 'producto_id' => 7, 'promocion_id' => null, 'cantidad' => 1, 'precioUnitario' => 6.00, 'opcion' => 'Recién horneado', 'subtotal' => 6.00],
            ['id' => 9, 'pedido_id' => 3, 'producto_id' => 10, 'promocion_id' => null, 'cantidad' => 1, 'precioUnitario' => 4.00, 'opcion' => 'Sin opción', 'subtotal' => 4.00],
            ['id' => 10, 'pedido_id' => 4, 'producto_id' => 6, 'promocion_id' => null, 'cantidad' => 2, 'precioUnitario' => 22.00, 'opcion' => 'Sin opción', 'subtotal' => 44.00],
            ['id' => 11, 'pedido_id' => 4, 'producto_id' => 9, 'promocion_id' => null, 'cantidad' => 1, 'precioUnitario' => 16.00, 'opcion' => 'Sin opción', 'subtotal' => 16.00],
            ['id' => 12, 'pedido_id' => 5, 'producto_id' => 1, 'promocion_id' => null, 'cantidad' => 2, 'precioUnitario' => 12.50, 'opcion' => 'Ambiente', 'subtotal' => 25.00],
            ['id' => 13, 'pedido_id' => 6, 'producto_id' => 6, 'promocion_id' => null, 'cantidad' => 2, 'precioUnitario' => 22.00, 'opcion' => 'Sin opción', 'subtotal' => 44.00],
            ['id' => 14, 'pedido_id' => 7, 'producto_id' => 8, 'promocion_id' => 2, 'cantidad' => 1, 'precioUnitario' => 10.50, 'opcion' => 'Sin opción', 'subtotal' => 10.50],
            ['id' => 15, 'pedido_id' => 7, 'producto_id' => 5, 'promocion_id' => null, 'cantidad' => 1, 'precioUnitario' => 6.00, 'opcion' => 'Frío', 'subtotal' => 6.00],
            ['id' => 16, 'pedido_id' => 8, 'producto_id' => 4, 'promocion_id' => null, 'cantidad' => 3, 'precioUnitario' => 11.00, 'opcion' => 'Sin opción', 'subtotal' => 33.00],
        ];

        foreach ($registros as $item) {
            DetallePedido::create($item);
        }
    }
}