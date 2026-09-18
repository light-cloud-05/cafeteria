<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bitacora;

class BitacorasSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['id' => 1, 'idUsuario' => 1, 'tablaAfectada' => 'Producto', 'idRegistroAfectado' => 1, 'accion' => 'UPDATE', 'valoresAnteriores' => 'Precio: 12.00 | Disponible: 1', 'valoresNuevos' => 'Precio: 12.50 | Disponible: 1', 'fechaHora' => '2026-09-01 08:30:00'],
            ['id' => 2, 'idUsuario' => 2, 'tablaAfectada' => 'Producto', 'idRegistroAfectado' => 9, 'accion' => 'UPDATE', 'valoresAnteriores' => 'Precio: 19.50 | Disponible: 1', 'valoresNuevos' => 'Precio: 18.00 | Disponible: 1', 'fechaHora' => '2026-09-02 09:15:00'],
            ['id' => 3, 'idUsuario' => 1, 'tablaAfectada' => 'Usuario', 'idRegistroAfectado' => 4, 'accion' => 'INSERT', 'valoresAnteriores' => 'NULL', 'valoresNuevos' => 'nombreUsuario: lrojas | idRol: 3', 'fechaHora' => '2026-09-02 11:00:00'],
            ['id' => 4, 'idUsuario' => 5, 'tablaAfectada' => 'MovimientoInventario', 'idRegistroAfectado' => 5, 'accion' => 'INSERT', 'valoresAnteriores' => 'NULL', 'valoresNuevos' => 'tipo: Ajuste | cantidad: -2.00', 'fechaHora' => '2026-09-10 16:00:00'],
            ['id' => 5, 'idUsuario' => 3, 'tablaAfectada' => 'CierreCaja', 'idRegistroAfectado' => 1, 'accion' => 'INSERT', 'valoresAnteriores' => 'NULL', 'valoresNuevos' => 'totalSistema: 350.50 | totalDeclarado: 350.50', 'fechaHora' => '2026-09-14 21:00:00'],
        ];

        foreach ($registros as $item) {
            Bitacora::create($item);
        }
    }
}