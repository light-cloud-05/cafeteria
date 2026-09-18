<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CierreCaja;

class CierreCajasSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['id' => 1, 'idUsuario' => 3, 'fechaInicio' => '2026-09-14 08:00:00', 'fechaFin' => '2026-09-14 21:00:00', 'totalSistema' => 350.50, 'totalDeclarado' => 350.50, 'diferencia' => 0.00, 'observacion' => 'Cierre turnos tarde y noche sin novedades'],
            ['id' => 2, 'idUsuario' => 4, 'fechaInicio' => '2026-09-14 08:00:00', 'fechaFin' => '2026-09-14 21:30:00', 'totalSistema' => 420.00, 'totalDeclarado' => 415.00, 'diferencia' => -5.00, 'observacion' => 'Faltante de 5 Bs por cambio incorrecto entregado'],
            ['id' => 3, 'idUsuario' => 3, 'fechaInicio' => '2026-09-15 08:00:00', 'fechaFin' => '2026-09-15 15:00:00', 'totalSistema' => 185.00, 'totalDeclarado' => 185.00, 'diferencia' => 0.00, 'observacion' => 'Arqueo parcial turno mañana'],
        ];

        foreach ($registros as $item) {
            CierreCaja::create($item);
        }
    }
}