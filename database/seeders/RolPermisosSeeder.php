<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RolPermiso;

class RolPermisosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolPermisos = [
            ['idRol' => 1, 'idPermiso' => 1, 'fechaAsignacion' => now()],
            ['idRol' => 1, 'idPermiso' => 2, 'fechaAsignacion' => now()],
            ['idRol' => 1, 'idPermiso' => 3, 'fechaAsignacion' => now()],
            ['idRol' => 1, 'idPermiso' => 4, 'fechaAsignacion' => now()],
            ['idRol' => 1, 'idPermiso' => 5, 'fechaAsignacion' => now()],
            ['idRol' => 2, 'idPermiso' => 2, 'fechaAsignacion' => now()],
            ['idRol' => 2, 'idPermiso' => 3, 'fechaAsignacion' => now()],
            ['idRol' => 2, 'idPermiso' => 4, 'fechaAsignacion' => now()],
            ['idRol' => 2, 'idPermiso' => 5, 'fechaAsignacion' => now()],
            ['idRol' => 3, 'idPermiso' => 3, 'fechaAsignacion' => now()],
            ['idRol' => 3, 'idPermiso' => 4, 'fechaAsignacion' => now()],
            ['idRol' => 4, 'idPermiso' => 2, 'fechaAsignacion' => now()],
            ['idRol' => 5, 'idPermiso' => 5, 'fechaAsignacion' => now()],
        ];

        foreach ($rolPermisos as $item) {
            RolPermiso::create($item);
        }
    }
}