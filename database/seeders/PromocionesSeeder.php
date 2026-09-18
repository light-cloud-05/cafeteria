<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Promocion;

class PromocionesSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['id' => 1, 'nombre' => 'Descuento Porcentaje', 'tipo' => 'Descuento Porcentaje', 'aplicaSobre' => 'Producto', 'valor' => 10.00, 'topeDescuento' => 50.00, 'fechaInicio' => '2026-09-01', 'fechaFin' => '2026-09-30', 'horaInicio' => '08:00:00', 'horaFin' => '22:00:00', 'activo' => 1],
            ['id' => 2, 'nombre' => 'Descuento Fijo', 'tipo' => 'Descuento Fijo', 'aplicaSobre' => 'Producto', 'valor' => 5.00, 'topeDescuento' => 20.00, 'fechaInicio' => '2026-09-10', 'fechaFin' => '2026-09-20', 'horaInicio' => '10:00:00', 'horaFin' => '18:00:00', 'activo' => 1],
            ['id' => 3, 'nombre' => 'Super Oferta Bebidas', 'tipo' => 'Super Oferta Bebidas', 'aplicaSobre' => 'Categoria', 'valor' => 15.00, 'topeDescuento' => 100.00, 'fechaInicio' => '2026-09-01', 'fechaFin' => '2026-10-15', 'horaInicio' => '00:00:00', 'horaFin' => '23:59:59', 'activo' => 1],
            ['id' => 4, 'nombre' => '2x1 Especial', 'tipo' => '2x1 Especial', 'aplicaSobre' => 'Producto', 'valor' => 50.00, 'topeDescuento' => 30.00, 'fechaInicio' => '2026-08-01', 'fechaFin' => '2026-08-31', 'horaInicio' => '08:00:00', 'horaFin' => '20:00:00', 'activo' => 0],
        ];

        foreach ($registros as $item) {
            Promocion::create($item);
        }
    }
}