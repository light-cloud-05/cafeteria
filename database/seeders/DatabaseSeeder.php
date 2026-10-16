<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $rolesExistentes = \App\Models\Rol::pluck('rol_id');

        $this->call([
            RolSeeder::class,
            PermisoSeeder::class,
            MetodoPagosSeeder::class,
        ]);

        $this->callWith(RolPermisosSeeder::class, [
            'rolesNuevos' => \App\Models\Rol::whereNotIn('rol_id', $rolesExistentes)->pluck('rol_id')->all(),
        ]);
    }
}
